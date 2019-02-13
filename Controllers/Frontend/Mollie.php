<?php

// Mollie Shopware Plugin Version: 1.4

use MollieShopware\Components\Logger;
use MollieShopware\Components\Notifier;
use MollieShopware\Components\Constants\PaymentStatus;
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
        return ['notify', 'return'];
    }

    /**
     * Index action method, is called after customer clicks the 'Confirm Order' button.
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        return $this->redirect([
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

        return $this->redirect(
            $paymentService->startTransaction(
                $order,
                $transaction,
                $orderDetails
            )
        );
    }

    /**
     * Returns the user from Mollie's checkout and processes his payment. If payment failed we restore
     * the basket. If payment succeeded we show the /checkout/finish page
     */
    public function returnAction()
    {
        /**
         * Get the type
         *
         * @var string $type
         */
        $type = $this->Request()->getParam('type');

        /**
         * Get the current order.
         *
         * @var \Shopware\Models\Order\Order $order
         */
        $order = $this->getOrder();

        /**
         * Check if the order is set, otherwise log and throw an error. The error is thrown
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
         * Create an instance of the PaymentService. The PaymentService is used
         * to handle transactions.
         *
         * @var \MollieShopware\Components\Services\PaymentService $paymentService
         */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        /**
         * Process the return
         */
        if ($type == 'order')
            return $this->processOrderReturn($order, $paymentService);

        if ($type == 'payment')
            return $this->processPaymentReturn($order, $paymentService);
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
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        /**
         * Try to retrieve the order, or return an error if the order
         * could not be retrieved.
         */
        try {
            $order = $this->getOrder();
        }
        catch(\Exception $ex) {
            Notifier::notifyException(
                $ex->getMessage()
            );
        }

        /**
         * Get the type of notification: order / payment
         */
        $type = $this->Request()->getParam('type');

        /**
         * Check the payment status for the order and notify the user.
         */
        try {
            $result = null;
            $transactionId = $this->Request()->getParam('id');

            if (strlen($transactionId) && substr($transactionId, 0, 3) == 'tr_')
                $result = $paymentService->checkPaymentStatus($order);
            else
                $result = $paymentService->checkOrderStatus($order);

            // log result
            Logger::log(
                'info',
                'Webhook for order ' . $order->getNumber() .
                (strlen($transactionId) ? ' (' . $transactionId . ')' : '') . ' has been called.'
            );

            if ($result) {
                Notifier::notifyOk(
                    'The payment status for order ' . $order->getNumber() . ' has been processed.'
                );
            } else {
                Notifier::notifyException(
                    'The payment status for order ' . $order->getNumber() . ' could not be processed.'
                );
            }
        }
        catch (\Exception $ex) {
            Notifier::notifyException(
                $ex->getMessage()
            );
        }
    }

    /**
     * Get the current order by orderNumber, taking into account
     * the session that started the order.
     *
     * @return null | boolean | \Shopware\Models\Order\Order
     * @throws \Exception
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
            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->getOrderRepository()->findOneBy([
                'number' => $orderNumber,
            ]);
        }
        catch (\Exception $ex) {
            Logger::log('error', $ex->getMessage(), $ex);
        }

        /**
         * Check if the order is set, otherwise log an error.
         */
        if (empty($order)) {
            Logger::log(
                'error',
                'The order with number ' . $orderNumber . ' could not be retrieved.'
            );

            return false;
        }

        /**
         * Get the transaction from the TransactionRepository, or log an error
         * when the transaction can't be retrieved.
         */
        try {
            /** @var \MollieShopware\Models\Transaction $transaction */
            $transaction = $this->getTransactionRepository()->findOneBy([
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
            Logger::log(
                'error',
                'The transaction for order ' . $orderNumber . ' could not be found.'
            );

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
            $this->getTransactionRepository()->save($transaction);
        }
        else {
            $message = 'The returned session ID does not match the session ID of the transaction. ' .
                'Therefore the customer is not automatically logged in.';

            if (empty($sessionId)) {
                $message = 'The returned transaction does not have a session ID.' .
                    'Therefore the customer is not automatically logged in.';
            }

            Logger::log('error', $message);
        }

        return $order;
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
     * Process order returns
     *
     * @var \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Components\Services\PaymentService $paymentService
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    private function processOrderReturn($order, $paymentService)
    {
        /**
         * Get Mollie's payment object from the PaymentService.
         *
         * @var \Mollie\Api\Resources\Order $molliePayment
         */
        try {
            $mollieOrder = $paymentService->getMollieOrder($order);
        } catch (\Exception $ex) {
            Logger::log(
                'error',
                'The order couldn\'t be retrieved.',
                null,
                true
            );
        }

        /**
         * Check payment status for order
         */
        try {
            $paymentService->checkPaymentStatusForOrder($order);
        }
        catch (\Exception $ex) {
            // log the error
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        // check the existing order status
        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_COMPLETELY_PAID)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED);

        // check if order is paid
        if ($mollieOrder->isPaid())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        // check if order is authorized
        if ($mollieOrder->isAuthorized())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED);

        // check if order is canceled
        if ($mollieOrder->isCanceled())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, 'order');

        // check if order has failed
        if ($paymentService->havePaymentsForOrderFailed($order))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_FAILED);
    }

    /**
     * Process payment returns
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Components\Services\PaymentService $paymentService
     * @throws Exception
     */
    private function processPaymentReturn($order, $paymentService)
    {
        /**
         * Get Mollie's payment object from the PaymentService.
         *
         * @var \Mollie\Api\Resources\Order $molliePayment
         */
        try {
            $molliePayment = $paymentService->getMolliePayment($order);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                'The payment couldn\'t be retrieved.',
                null,
                true
            );
        }

        // check the existing order status
        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_COMPLETELY_PAID)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED);

        // check if payment is paid
        if ($molliePayment->isPaid())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        // check if payment is authorized
        if ($molliePayment->isAuthorized())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED);

        // check if payment is canceled
        if ($molliePayment->isCanceled())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED);

        // check if payment has failed
        if ($molliePayment->isFailed())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_FAILED);
    }

    /**
     * @param string $status
     * @param \Shopware\Models\Order\Order $order
     * @param string $type
     * @throws \Exception
     */
    private function processPaymentStatus($order, $status, $type = 'payment')
    {
        /**
         * Create an instance of the PaymentService. The PaymentService is used
         * to handle transactions.
         *
         * @var \MollieShopware\Components\Services\PaymentService $paymentService
         */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        /**
         * Set payment status
         */
        $paymentService->setPaymentStatus($order, $status, false, $type);

        /**
         * Send the order confirmation e-mail
         */
        if ($status == PaymentStatus::MOLLIE_PAYMENT_PAID ||
            $status == PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED) {

            try {
                $this->sendConfirmationEmail($order);
            } catch (\Exception $ex) {
                // log the error
                Logger::log(
                    'error',
                    $ex->getMessage(),
                    $ex
                );
            }
        }

        /**
         * Redirect customer to finish page on successful payment
         */
        if ($status == PaymentStatus::MOLLIE_PAYMENT_PAID ||
            $status == PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED) {
            return $this->redirectToFinish($order->getTemporaryId());
        }

        /**
         * Create an instance of the BasketService. The BasketService
         * is used to restore the basket after a payment failed.
         *
         * @var \MollieShopware\Components\Services\BasketService $basketService
         */
        if ($status == PaymentStatus::MOLLIE_PAYMENT_FAILED) {
            $basketService = Shopware()->Container()
                ->get('mollie_shopware.basket_service');

            $basketService->restoreBasket($order);

            return $this->redirectBack('Payment failed');
        }
    }

    /**
     * Send a confirmation e-mail once the order is processed.
     *
     * @param \Shopware\Models\Order\Order $order
     * @throws \Exception
     */
    private function sendConfirmationEmail($order)
    {
        /**
         * Create an instance of the core sOrder class. The sOrder
         * class is used to send the confirmation e-mail.
         *
         * @var $sOrder
         */
        $sOrder = Shopware()->Modules()->Order();

        /**
         * Get the most recent transaction for the order from the TransactionRepository.
         *
         * @var \MollieShopware\Models\Transaction $transaction
         */
        $transaction = $this->getTransactionRepository()->getMostRecentTransactionForOrder($order);

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
                $this->getTransactionRepository()->save($transaction);
            }
            catch (\Exception $ex) {
                Logger::log('error', $ex->getMessage(), $ex);
            }
        }
    }
}