<?php

	// Mollie Shopware Plugin Version: 1.2.3

    use MollieShopware\Components\Base\AbstractPaymentController;
    use MollieShopware\Components\Constants\PaymentStatus;
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
             * @var \MollieShopware\Components\Mollie\OrderService $order_service
             * @var \MollieShopware\Components\Mollie\PaymentService $payment_service
             * @var \Shopware\Bundle\AttributeBundle\Repository\OrderRepository $order_repository
             * @var int $transaction_id
             */
            $payment_service = Shopware()->Container()->get('mollie_shopware.payment_service');

            /*
             * Persist basket from session to database, returning it's signature which
             * is then used to save the basket to an order.
             * */
            $signature = $this->doPersistBasket();

            /**
             * Create payment transaction in the database
             */
            $transaction_id = $payment_service->createTransaction($this, $signature)->getID();

            /*
             * Save our current order in the database. This returns an order
             * number which we can use in our payment description.
             *
             * We do NOT send a thank you email at this point. Payment status
             * remains OPEN for now.
             * */
            $order_number = $this->saveOrder($transaction_id, $signature, PaymentStatus::OPEN, false);

            /*
             * Get $order Doctrine model, which is easier to handle than
             * the sOrder class
             * */

            $order_repository = Shopware()->Container()->get('models')->getRepository(Order::class);

            // find order
            $order = $order_repository->findOneBy([
                'number' => $order_number,
            ]);


            if (empty($order)) {
                // @todo: this deserves a more describing error message
                throw new \Exception('order error');
            }

            return $this->redirect($payment_service->startTransaction($order));

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
             * @var \MollieShopware\Components\Mollie\PaymentService $payment_service
             **/

            $payment_service = Shopware()->Container()->get('mollie_shopware.payment_service');
            $order = $this->getOrder();

            if ($payment_service->checkPaymentStatus($order)) {
                return $this->redirectBack('Payment failed');
            }
            else {
                return $this->redirectToFinish();
            }

        }

        /**
         * Background process for Mollie callbacks
         */
        public function notifyAction()
        {

            /**
             * @var \MollieShopware\Components\Mollie\PaymentService $payment_service
             **/
            $payment_service = Shopware()->Container()->get('mollie_shopware.payment_service');

            try{
                $order = $this->getOrder();
            }
            catch(\Exception $e){
                return $this->notifyException($e->getMessage());
            }

            if ($payment_service->checkPaymentStatus($order)) {
                return $this->notifyOK('Thank you');
            }
            else {
                return $this->notifyOK('Thank you');
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
             * @var \MollieShopware\Components\Mollie\PaymentService $payment_service
             * @var string $order_number
             * @var string $ts
             * @var string $checksum
             */

            $payment_service = Shopware()->Container()->get('mollie_shopware.payment_service');


            // load (selected) request variables to local variables
            foreach(['order_number', 'checksum', 'ts'] as $var){ $$var = $this->request()->getParam($var); }

            $order_repository = Shopware()->Container()->get('models')->getRepository(Order::class);

            // find order
            $order = $order_repository->findOneBy([
                'number' => $order_number,
            ]);

            if (empty($order)) {
                // @todo: this deserves a more describing error message
                throw new \Exception('order error');
            }

            if ($payment_service->generateChecksum($order, $ts) !== $checksum){
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
