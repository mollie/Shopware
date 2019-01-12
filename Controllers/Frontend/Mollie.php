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

        /**
         * Create an instance of the PaymentService. The PaymentService is used
         * to handle transactions.
         *
         * @var \MollieShopware\Components\Services\PaymentService $paymentService
         */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        /**
         * Persist the basket from session to database, returning it's signature which
         * is then used to save the basket to an order.
         *
         * @var string $signature
         */
        $signature = $this->doPersistBasket();

        /**
         * Create the Mollie transaction.
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

        /**
         * Create an OrderService instance. The OrderService is used to retrieve orders,
         * order lines for mollie, etc.
         *
         * @var \MollieShopware\Components\Services\OrderService $orderService
         */
        $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

        /**
         * Get the order by order number from the OrderService.
         *
         * @var \Shopware\Models\Order\Order $order */
        $order = $orderService->getOrderByNumber($orderNumber);

        /**
         * Check if the order is set, otherwise log an throw an error. The error is thrown
         * to also tell the customer that something went wrong.
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
         * Get the order lines of the order from the OrderService.
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
        /**
         * Create an instance of the PaymentService. The PaymentService is used
         * to handle transactions.
         *
         * @var \MollieShopware\Components\Services\PaymentService $paymentService
         */
        $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

        /**
         * Get the current order.
         *
         * @var \Shopware\Models\Order\Order $order
         */
        $order = $this->getOrder();

        /**
         * Check if the order is set, otherwise log an throw an error. The error is thrown
         * to also tell the customer that something went wrong.
         */
        if (empty($order) || $order == false || !$order instanceof \Shopware\Models\Order\Order) {
            Logger::log(
                'error',
                'The order couldn\'t be retrieved.',
                null,
                true
            );
        }

        /**
         * Get Mollie's payment object from the PaymentService.
         *
         * @var \Mollie\Api\Resources\Order $molliePayment
         */
        $molliePayment = $paymentService->getPaymentObject($order);

        /**
         * Generate the URL of the finish page to redirect the customer to.
         *
         * @var string $finishUrl
         */
        $finishUrl = Shopware()->Front()->Router()->assemble([
            'controller' => 'checkout',
            'action' => 'finish',
            'sUniqueID' => $order->getTemporaryId()
        ]);

        /**
         * Send the confirmation e-mail if the payment is complete.
         */
        if ($this->isComplete($molliePayment))
            $this->sendConfirmationEmail($order);

        /**
         * Set the payment status of the order and redirect the customer.
         *
         * We use the deprecated sOrder class to set the payment status
         * for a paid order to also send a payment status e-mail. This might
         * need to be reworked later.
         */
        if ($molliePayment->isPaid()) {
            Shopware()->Modules()->Order()->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_COMPLETELY_PAID,
                true
            );

            $this->persistOrder($order);

            return $this->redirect($finishUrl);
        }

        elseif ($molliePayment->isAuthorized()) {
            $order->setPaymentStatus(
                Shopware()->Models()->find('Shopware\Models\Order\Status', Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED)
            );

            $this->persistOrder($order);

            return $this->redirect($finishUrl);
        }

        elseif ($molliePayment->isCreated() && $molliePayment->method == 'banktransfer') {
            return $this->redirect($finishUrl);
        }

        else {
            $basketService = Shopware()->Container()->get('mollie_shopware.basket_service');
            $basketService->restoreBasket($order);

            return $this->redirect(
                Shopware()->Front()->Router()->assemble([
                    'controller' => 'checkout',
                    'action' => 'confirm'
                ])
            );
        }
    }

    /**
     * Send a confirmation e-mail once the order is processed.
     *
     * @param \Shopware\Models\Order\Order\ $order
     * @throws \Exception
     */
    public function sendConfirmationEmail($order)
    {
        /**
         * Create an instance of the core sOrder class. The sOrder
         * class is used to send the confirmation e-mail.
         *
         * @var $sOrder
         */
        $sOrder = Shopware()->Modules()->Order();

        /**
         * Create an instance of the TransactionRepository. The TransactionRepository is used to
         * get transactions from the database.
         *
         * @var \MollieShopware\Models\TransactionRepository $transactionRepo
         */
        $transactionRepo = Shopware()->Models()->getRepository(
            \MollieShopware\Models\Transaction::class
        );

        /**
         * Get the most recent transaction for the order from the TransactionRepository.
         *
         * @var \MollieShopware\Models\Transaction $transaction
         */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        if (!empty($transaction)) {
            /**
             * Get the variables for the order mail from the transaction. The order mail variables
             * are returned as JSON value, we decode that JSON to an array here.
             *
             * @var array $variables
             */
            $variables = @json_decode($transaction->getOrdermailVariables(), true);

            /**
             * Send the confirmation e-mail using the retrieved variables
             * or log an error if the e-mail could not be sent.
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
             * changes could not be saved.
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
     * Background process for Mollie callbacks.
     */
    public function notifyAction()
    {
        /** @var \Shopware\Models\Order\Order $order */
        $order = null;

        /**
         * Create an instance of the PaymentService. The PaymentService is used
         * to handle transactions.
         *
         * @var \MollieShopware\Components\Services\PaymentService $paymentService
         */
        $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

        /**
         * Try to retrieve the order, or return an error if the order
         * could not be retrieved.
         */
        try {
            $order = $this->getOrder();
        }

        catch(\Exception $ex){
            return $this->notifyException($ex->getMessage());
        }

        /**
         * Check the payment status for the order and notify the user.
         */
        if ($paymentService->checkPaymentStatus($order)) {
            return $this->notifyOK('Thank you!');
        }

        else {
            return $this->notifyException('The payment status could not be updated.');
        }
    }

    /**
     * Start a session with a given sessiond ID.
     *
     * Close the current session, set the ID to the given sessionId
     * and start the session.
     *
     * @param $sessionId
     */
    private function startSession($sessionId) {
        \Enlight_Components_Session::writeClose();
        \Enlight_Components_Session::setId($sessionId);
        \Enlight_Components_Session::start();
    }

    /**
     * Get the current order by orderNumber, taking into account
     * the session that started the order.
     *
     * @return null | boolean | \Shopware\Models\Order\Order
     * @throws Exception
     */
    private function getOrder()
    {
        $order = null;
        $transaction = null;
        $sessionId = $this->Request()->getParam('session-1');
        $orderNumber = $this->Request()->getParam('orderNumber');

        /**
         * Get the order from the OrderRepository, or log an error
         * when the order can't be retrieved.
         */
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

        /**
         * Check if the order is set, otherwise log an error.
         */
        if (empty($order)) {
            Logger::log('error', 'The order with number ' . $orderNumber . ' could not be retrieved.');
            return false;
        }

        /**
         * Get the transaction from the TransactionRepository, or log an error
         * when the transaction can't be retrieved.
         */
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

        /**
         * Check if the transaction is set, otherwise log an error.
         */
        if (empty($transaction)) {
            Logger::log('error', 'The transaction for order ' . $orderNumber . ' could not be found.');

            return false;
        }

        /**
         * Check if the returned session matches the transaction's session. If there is a match,
         * restore that session and clear the stored session from the transaction. This is done to
         * prevent that Mollie's return URL for the confirmation is used ever again.
         *
         * If there is no match, log the error and move on.
         */
        if ($transaction->getSessionId() == $sessionId) {
            $this->startSession($sessionId);
            $transaction->setSessionId(null);
            $transactionRepo->save($transaction);
        }

        else {
            $message = 'The returned session ID does not match the session ID of the transaction.';

            if (empty($sessionId))
                $message = 'The returned transaction does not have a session ID.';

            Logger::log('error', $message);
        }

        return $order;
    }

    /**
     * Persist the order model.
     *
     * @param \Shopware\Models\Order\Order $order
     */
    public function persistOrder($order)
    {
        Shopware()->Models()->persist($order);
        Shopware()->Models()->flush();
    }

    /**
     * Return whether the Mollie payment is completed.
     *
     * @param $molliePayment
     * @return bool
     */
    private function isComplete($molliePayment) {
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
     * Shows a JSON thank you message, with a 200 HTTP ok.
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
     * Get the issuers for the iDEAL payment method.
     * Called in an ajax call on the frontend.
     */
    public function idealIssuersAction()
    {
        /**
         * Prevent this action from being stored or cached.
         */
        $this->setNoRender();

        /**
         * Get the issuers from the IdealService, or return an error.
         */
        try {
            /** @var \MollieShopware\Components\Services\IdealService $ideal */
            $idealService = $this->container->get('mollie_shopware.ideal_service');

            /** @var array $idealIssuers */
            $idealIssuers = $idealService->getIssuers();

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
     * and cannot be called from outside.

     * @return string
     */
    public function doPersistBasket()
    {
        return parent::persistBasket();
    }
}
