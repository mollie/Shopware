<?php

	// Mollie Shopware Plugin Version: 1.3.12

use MollieShopware\Components\Logger;
use MollieShopware\Components\Base\AbstractPaymentController;
use Shopware\Models\Order\Status;

class Shopware_Controllers_Frontend_Mollie extends AbstractPaymentController
{
    /**
     * Whitelist webhookAction from CSRF protection
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return ['notify'];
    }

    /**
     * Index action method, is called after customer clicks the 'Confirm Order' button.
     * Forwards to the correct action.
     *
     * @return mixed
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
        // @todo: check if basket exists!!

        /** @var \MollieShopware\Components\Mollie\PaymentService $paymentService */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        /**
         * Persist basket from session to database, returning it's signature which
         * is then used to save the basket to an order.
         */
        $signature = $this->doPersistBasket();

        /**
         * Create the Mollie transaction
         *
         * @var \MollieShopware\Models\Transaction $transaction
         */
        $transaction = $paymentService->createTransaction();

        /**
         * Save our current order in the database. This returns an order
         * number which we can use in our payment description.
         *
         * We do NOT send a thank you email at this point. Payment status
         * remains OPEN for now.
         *
         * @var string $orderNumber
         */
        $orderNumber = $this->saveOrder(
            $transaction->getTransactionId(),
            $signature,
            Status::PAYMENT_STATE_OPEN,
            false
        );

        /** @var \MollieShopware\Components\Mollie\OrderService $orderService */
        $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

        /**
         * Get the order by the order number
         *
         * @var \Shopware\Models\Order\Order $order
         */
        $order = $orderService->getOrderByNumber($orderNumber);

        /**
         * Log an error if the order could not be found
         */
        if (empty($order)) {
            Logger::log(
                'error',
                'The order with order number ' . $orderNumber . ' could not be found.',
                null,
                true
            );
        }

        /**
         * Get the order lines of the order
         *
         * @var array
         */
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
        /** @var \MollieShopware\Components\Mollie\PaymentService $paymentService */
        $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $this->getOrder();

        /**
         * Check if the order is set, if not throw an error
         */
        if (empty($order) || $order == false || !$order instanceof \Shopware\Models\Order\Order) {
            Logger::log(
                'error',
                'The order couldn\'t be retrieved.',
                null,
                true
            );
        }

        /** @var \Mollie\Api\Resources\Order $molliePayment */
        $molliePayment = $paymentService->getPaymentObject($order);

        $sOrder = Shopware()->Modules()->Order();
        $baseUrl = Shopware()->Front()->Request()->getBaseUrl();

        /**
         * Send the confirmation e-mail
         */
        if ($this->isComplete($molliePayment))
            $this->sendConfirmationEmail();

        /**
         * Set the payment status of the order and redirect the customer
         */
        if ($molliePayment->isPaid()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_COMPLETELY_PAID, true);
            return $this->redirect($baseUrl . '/checkout/finish?sUniqueID=' . $order->getTemporaryId());
        }

        elseif ($molliePayment->isAuthorized()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED);
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
     * Send confirmation e-mail
     *
     * @param \Shopware\Models\Order\Order\ $order
     * @throws \Exception
     */
    public function sendConfirmationEmail($order)
    {
        $sOrder = Shopware()->Modules()->Order();

        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->Models()->getRepository(Transaction::class);

        /** @var \MollieShopware\Models\Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        if (!empty($transaction)) {
            /**
             * Get the variables for the order mail
             *
             * @var array $variables
             */
            $variables = @json_decode($transaction->getOrdermailVariables(), true);

            /**
             * Send the confirmation e-mail using the retrieved variables
             * or log an error if the e-mail could not be sent
             */
            if (is_array($variables)) {
                try {
                    $sOrder->sUserData = $variables;
                    $sOrder->sendMail($variables);
                }
                catch (\Exception $ex) {
                    Logger::log('error', $ex->getMessage(), $ex);
                }
            }

            /**
             * Clear the order mail variables from the transaction
             * as they are no longer needed, or log an error if the
             * changes could not be saved
             */
            try {
                $transaction->setOrdermailVariables(null);
                $transactionRepo->save($transaction);
            }
            catch (\Exception $ex) {
                Logger::log('error', $ex->getMessage(), $ex);
            }
        }
    }

    /**
     * Background process for Mollie callbacks
     */
    public function notifyAction()
    {
        /** @var \Shopware\Models\Order\Order $order */
        $order = null;

        /** @var \MollieShopware\Components\Mollie\PaymentService $paymentService */
        $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

        /**
         * Try to retrieve the order, or return an error if the order
         * could not be retrieved
         */
        try {
            $order = $this->getOrder();
        }
        catch(\Exception $ex){
            return $this->notifyException($ex->getMessage());
        }

        /**
         * Check the payment status for the order and notify the user
         */
        if ($paymentService->checkPaymentStatus($order)) {
            return $this->notifyOK('Thank you');
        }
        else {
            return $this->notifyException('The payment status could not be updated.');
        }
    }

    /**
     * Start a session with a given sessiond ID
     *
     * @param $sessionId
     */
    private function startSession($sessionId) {
        /**
         * Close the current session, set the ID to the given sessionId
         * and start the session
         */
        \Enlight_Components_Session::writeClose();
        \Enlight_Components_Session::setId($sessionId);
        \Enlight_Components_Session::start();
    }

    /**
     * Get the current order by request parameter, taking into account
     * the checksum and the timestamp/salt (ts)
     * @return mixed
     * @throws Exception
     */
    private function getOrder()
    {
        // vars
        $order = null;
        $transaction = null;
        $sessionId = $this->Request()->getParam('session-1');
        $orderNumber = $this->Request()->getParam('orderNumber');

        try {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = Shopware()->Container()->get('models')->getRepository(
                \Shopware\Models\Order\Order::class
            );

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderRepo->findOneBy([
                'number' => $orderNumber,
            ]);
        }
        catch (Exception $ex) {
            Logger::log('error', $ex->getMessage(), $ex);
        }

        if (empty($order)) {
            Logger::log('error', 'The order with number ' . $orderNumber . ' could not be retrieved.');

            return false;
        }

        try {
            /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
            $transactionRepo = Shopware()->Container()->get('models')->getRepository(
                \MollieShopware\Models\Transaction::class
            );

            /** @var \MollieShopware\Models\Transaction $transaction */
            $transaction = $transactionRepo->findOneBy([
                'transactionId' => $order->getTransactionId()
            ]);
        }
        catch (\Exception $ex) {
            Logger::log('error', $ex->getMessage(), $ex);
        }

        if (empty($transaction)) {
            Logger::log('error', 'The transaction for order ' . $orderNumber . ' could not be found.');

            return false;
        }

        // check if the returned session matches the transaction's session
        if ($transaction->getSessionId() == $sessionId) {
            // start the session
            $this->startSession($sessionId);

            // remove the session from the transaction
            $transaction->setSessionId(null);

            // save the transaction
            $transactionRepo->save($transaction);
        } else {
            // set the message
            $message = 'The returned session ID does not match the session ID of the transaction.';

            if (empty($sessionId))
                $message = 'The returned transaction does not have a session ID.';

            // log the error
            Logger::log('error', $message);

            return false;
        }

        return $order;
    }

    /**
     * Return whether the Mollie payment is completed
     *
     * @param $molliePayment
     * @return bool
     */
    private function isComplete($molliePayment) {
        // check if order is complete
        if ($molliePayment->isPaid() ||
            $molliePayment->isAuthorized() ||
            ($molliePayment->isCreated() && $molliePayment->method == 'banktransfer')) {
            return true;
        }

        return false;
    }

    /**
     * Shows a JSON exception for the given request. Also sends
     * a 500 server error.
     *
     * @param $error
     */
    private function notifyException($error) {
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
    private function notifyOK($msg) {
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
            /** @var \MollieShopware\PaymentMethods\Ideal $ideal */
            $ideal = $this->container->get('mollie_shopware.payment_methods.ideal');

            /** @var array $idealIssuers */
            $idealIssuers = $ideal->getIssuers();

            return $this->sendResponse([
                'data' => $idealIssuers,
                'success' => true
            ]);
        }
        catch (\Exception $ex) {
            return $this->sendResponse([
                'message' => $ex->getMessage(),
                'success' => false ],
                500
            );
        }
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
