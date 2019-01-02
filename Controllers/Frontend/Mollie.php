<?php

	// Mollie Shopware Plugin Version: 1.3.10.1

use MollieShopware\Components\Logger;
use MollieShopware\Components\Base\AbstractPaymentController;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Models\Order\Order;

    class Shopware_Controllers_Frontend_Mollie extends AbstractPaymentController
    {
        /**
         * Whitelist webhookAction from CSRF protection
         */
        public function getWhitelistedCSRFActions()
        {
            return [ 'notify' ];
        }

        /**
         * Index action method.
         *
         * Is called after customer clicks the 'Confirm Order' button
         *
         * Forwards to the correct action.
         */
        public function indexAction()
        {

            $this->redirect([

                'action'        => 'direct',
                'forceSecure'   => true,

            ]);

        }

        /**
         * Creates the transaction with Mollie and sends the
         * user to its checkout page
         *
         * @throws Exception
         */
        public function directAction()
        {
            /**
             * @todo: check if basket exists!!
             */

            /**
             * @var \MollieShopware\Components\Mollie\PaymentService $paymentService
             * @var \Shopware\Bundle\AttributeBundle\Repository\OrderRepository $orderRepo
             * @var \MollieShopware\Models\Transaction $transaction
             */
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

            /*
             * Persist basket from session to database, returning it's signature which
             * is then used to save the basket to an order.
             * */
            $signature = $this->doPersistBasket();

            /**
             * Create payment transaction in the database
             */
            $transaction = $paymentService->createTransaction();

            /*
             * Save our current order in the database. This returns an order
             * number which we can use in our payment description.
             *
             * We do NOT send a thank you email at this point. Payment status
             * remains OPEN for now.
             * */
            $orderNumber = $this->saveOrder($transaction->getTransactionID(), $signature, PaymentStatus::OPEN, false);

            $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

            // find order
            $order = $orderService->getOrderByNumber($orderNumber);

            if (empty($order)) {
                // @todo: this deserves a more describing error message
                throw new \Exception('order error');
            }

            $orderDetails = $orderService->getOrderLines($order);

            return $this->redirect($paymentService->startTransaction($order, $transaction, $orderDetails));
        }

        /**
         * Returns the user from Mollie's checkout and
         * processes his payment. If payment failed we
         * restore the basket and enable retrying. If
         * payment succeeded we show the /checkout/finish
         * page
         */
        public function returnAction()
        {

            /**
             * @var \MollieShopware\Components\Mollie\PaymentService $paymentService
             * @var \Shopware\Bundle\AttributeBundle\Repository\OrderRepository $orderRepo
             * @var \MollieShopware\Components\Mollie\BasketService $basketService
             * @var Order $order
             */
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

            $order = $this->getOrder();

            $molliePayment = $paymentService->getPaymentObject($order);

            $baseUrl = Shopware()->Front()->Request()->getBaseUrl();

            $sOrder = Shopware()->Modules()->Order();

            /** @var TransactionRepository $transactionRepo */
            $transactionRepo = Shopware()->Models()->getRepository(Transaction::class);

            /** @var Transaction $transaction */
            $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

            // send order confirmation
            if (!empty($transaction) &&
                ($molliePayment->isPaid() ||
                $molliePayment->isAuthorized() ||
                ($molliePayment->isCreated() && $molliePayment->method == 'banktransfer'))) {
                $variables = @json_decode($transaction->getOrdermailVariables(), true);

                if (is_array($variables)) {
                    $sOrder->sUserData = $variables;
                    $sOrder->sendMail($variables);
                }

                try {
                    $transaction->setOrdermailVariables(null);
                    $transactionRepo->save($transaction);
                }
                catch (Exception $ex) {
                    // write exception to log
                    Logger::log('error', $ex->getMessage(), $ex);
                }
            }

            // set payment status and redirect
            if ($molliePayment->isPaid()) {
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::PAID, true);
                return $this->redirect($baseUrl . '/checkout/finish?sUniqueID=' . $order->getTemporaryId());
            }
            elseif ($molliePayment->isAuthorized()) {
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::THE_CREDIT_HAS_BEEN_ACCEPTED);
                return $this->redirect($baseUrl . '/checkout/finish?sUniqueID=' . $order->getTemporaryId());
            }
            elseif ($molliePayment->isCreated() && $molliePayment->method == 'banktransfer') {
                return $this->redirect($baseUrl . '/checkout/finish?sUniqueID=' . $order->getTemporaryId());
            }
            else {
                $basketService = Shopware()->Container()->get('mollie_shopware.basket_service');
                $basketService->restoreBasket($order);

                return $this->redirect($baseUrl . '/checkout/confirm');
            }
        }

        /**
         * Background process for Mollie callbacks
         */
        public function notifyAction()
        {

            /**
             * @var \MollieShopware\Components\Mollie\PaymentService $paymentService
             **/
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

            try{
                $order = $this->getOrder();
            }
            catch(\Exception $e){
                return $this->notifyException($e->getMessage());
            }

            if ($paymentService->checkPaymentStatus($order)) {
                return $this->notifyOK('Thank you');
            }
            else {
                return $this->notifyException('The payment status could not be updated.');
            }

        }

        /**
         * Get the current order by request parameter, taking into account
         * the checksum and the timestamp/salt (ts)
         * @return mixed
         * @throws Exception
         */
        private function getOrder()
        {

            /**
             * @var \MollieShopware\Components\Mollie\PaymentService $paymentService
             * @var string $order_number
             * @var string $ts
             * @var string $checksum
             */

            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');


            // load (selected) request variables to local variables
            foreach(['order_number', 'checksum', 'ts'] as $var){ $$var = $this->request()->getParam($var); }

            $orderRepo = Shopware()->Container()->get('models')->getRepository(Order::class);

            // find order
            $order = $orderRepo->findOneBy([
                'number' => $order_number,
            ]);

            if (empty($order)) {
                // @todo: this deserves a more describing error message
                throw new \Exception('order error');
            }

            if ($paymentService->generateChecksum($order, $ts) !== $checksum){
                // @todo: this deserves a more describing error message
                throw new \Exception('order error: bad checksum');
            }

            return $order;
        }

        /**
         * Shows a JSON exception for the given request. Also sends
         * a 500 server error.
         *
         * @param $error
         */
        private function notifyException($error){

            header('HTTP/1.0 500 Server Error');
            header('Content-Type: text/json');
            echo json_encode(['success'=>false, 'message'=>$error], JSON_PRETTY_PRINT);
            die();

        }

        /**
         * Shows a JSON thank you message, with a 200 HTTP ok
         *
         * @param $msg
         */
        private function notifyOK($msg){

            header('HTTP/1.0 200 Ok');
            header('Content-Type: text/json');
            echo json_encode(['success'=>true, 'message'=>$msg], JSON_PRETTY_PRINT);
            die();

        }

        /**
         * Get the issuers for the iDEAL payment method
         * Called in an ajax call on the frontend
         */
        public function idealIssuersAction()
        {

            $this->setNoRender();

            try {
                $ideal = $this->container->get('mollie_shopware.payment_methods.ideal');

                $idealIssuers = $ideal->getIssuers();

                return $this->sendResponse([ 'data' => $idealIssuers, 'success' => true ]);
            }
            catch (Exception $ex) {
                return $this->sendResponse([ 'message' => $ex->getMessage(), 'success' => false ], 500);
            }
        }

        /**
         * Get the id of the chosen ideal issuer from database
         */
        protected function getIdealIssuer()
        {
            $ideal = $this->container->get('mollie_shopware.payment_methods.ideal');
            return $ideal->getSelectedIssuer();
        }

        /**
         * Sets a session variable
         *
         * @param string $variable The variable to be set in Session storage
         * @param mixed $value The variable's value
         */
        protected function session($variable, $value)
        {

            Shopware()->Session()
                ->$variable = $value;

        }

        /**
         * Sends the user back to the payment screen with the given error
         *
         * @param $error
         */
        protected function paymentError($error)
        {
            $this->session('mollieStatusError', $error);
            $this->redirectBack();
        }


        /**
         * Return the ISO code for the currency that's being used
         */
        public function getCurrencyISO($default = 'EUR')
        {
            $basket = $this->getBasket();

            return $basket ? $basket['sCurrencyName'] : $default;
        }


        protected function loadBasketFromSignature($signature)
        {
            return parent::loadBasketFromSignature($signature);
        }


        /**
         * Wrapper function for persistbasket, which is declared protected
         * and cannot be called from outside

         * @return string
         */
        public function doPersistBasket()
        {
            return parent::persistBasket();
        }

    }
