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
        /** @var string $type */
        $type = $this->Request()->getParam('type');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $this->getOrder();

        if (empty($order) || $order == false || !$order instanceof \Shopware\Models\Order\Order) {
            Logger::log(
                'error',
                'The order couldn\'t be retrieved.',
                null,
                true
            );
        }

        /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        $this->view->assign('orderNumber', $order->getNumber());

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

        /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        try {
            $order = $this->getOrder();
        }
        catch(\Exception $ex) {
            Notifier::notifyException(
                $ex->getMessage()
            );
        }

        // check the payment status for the order and notify the user.
        try {
            $result = null;
            $type = $this->Request()->getParam('type');
            $paymentId = $this->Request()->getParam('id');

            if (substr($paymentId, 0, strlen('tr_')) != 'tr_')
                $paymentId = null;

            if ($type == 'order' && $paymentId == null)
                $result = $paymentService->checkOrderStatus($order);
            else
                $result = $paymentService->checkPaymentStatus($order, $paymentId);

            // log result
            Logger::log(
                'info',
                'Webhook for order ' . $order->getNumber() . ' has been called.'
            );

            if ($result) {
                Notifier::notifyOk(
                    'The payment status for order ' . $order->getNumber() . ' has been processed.'
                );
            }
            else {
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
     * Route to retry making an order
     *
     * @throws Exception
     */
    public function retryAction()
    {
        $orderNumber = $this->Request()->getParam('orderNumber');

        /** @var \MollieShopware\Components\Services\OrderService $orderService */
        $orderService = Shopware()->Container()
            ->get('mollie_shopware.order_service');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $orderService->getOrderByNumber($orderNumber);

        /** @var \MollieShopware\Components\Services\BasketService $basketService */
        $basketService = Shopware()->Container()
            ->get('mollie_shopware.basket_service');

        if (!empty($order))
            $basketService->restoreBasket($order);

        return $this->redirectBack();
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
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
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
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
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

        return $order;
    }

    /**
     * Get the issuers for the iDEAL payment method.
     * Called in an ajax call on the frontend.
     */
    public function idealIssuersAction()
    {
        // prevent this action from being stored or cached
        $this->setNoRender();

        // get the issuers from the IdealService, or return an error
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
     * @param \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Components\Services\PaymentService $paymentService
     *
     * @return mixed
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    private function processOrderReturn(
        \Shopware\Models\Order\Order $order,
        \MollieShopware\Components\Services\PaymentService $paymentService
    )
    {
        /** @var \Mollie\Api\Resources\Order $molliePayment */
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

        // check payment status for order
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

        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED)
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

        // check if order payments have failed
        if ($paymentService->isOrderPaymentsStatus($order, PaymentStatus::MOLLIE_PAYMENT_FAILED))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_FAILED);

        // check if order payments are canceled
        if ($paymentService->isOrderPaymentsStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED);

        return false;
    }

    /**
     * Process payment returns
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Components\Services\PaymentService $paymentService
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function processPaymentReturn($order, $paymentService)
    {
        /** @var \Mollie\Api\Resources\Order $molliePayment */
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

        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED);

        // check if payment is paid
        if ($molliePayment->isPaid())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        // check if payment is authorized
        if ($molliePayment->isAuthorized())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED);

        // check if payment is pending
        if ($molliePayment->isPending())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED);

        // check if payment is open
        if ($molliePayment->isOpen())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_OPEN);

        // check if payment is canceled
        if ($molliePayment->isCanceled())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED);

        // check if payment is expired
        if ($molliePayment->isExpired())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_EXPIRED);

        // check if payment has failed
        if ($molliePayment->isFailed())
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_FAILED);

        return false;
    }

    /**
     * Process the payment status for an order
     *
     * @param string $status
     * @param \Shopware\Models\Order\Order $order
     * @param string $type
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function processPaymentStatus(
        \Shopware\Models\Order\Order $order,
        $status,
        $type = 'payment'
    )
    {
        /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
        $paymentService = Shopware()->Container()
            ->get('mollie_shopware.payment_service');

        $paymentService->setPaymentStatus($order, $status, false, $type);

        // send the order confirmation e-mail
        if ($status == PaymentStatus::MOLLIE_PAYMENT_PAID ||
            $status == PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED ||
            $status == PaymentStatus::MOLLIE_PAYMENT_DELAYED ||
            $status == PaymentStatus::MOLLIE_PAYMENT_OPEN) {

            try {
                $this->sendConfirmationEmail($order);
            }
            catch (\Exception $ex) {
                // log the error
                Logger::log(
                    'error',
                    $ex->getMessage(),
                    $ex
                );
            }
        }

        // redirect customer to finish page on successful payment
        if ($status == PaymentStatus::MOLLIE_PAYMENT_PAID ||
            $status == PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED) {
            return $this->redirectToFinish($order->getTemporaryId());
        }

        // redirect customer to finish page on created payment
        if ($status == PaymentStatus::MOLLIE_PAYMENT_OPEN)
            return $this->redirectToFinish($order->getTemporaryId());

        // redirect customer to finish page on pending payment
        if ($status == PaymentStatus::MOLLIE_PAYMENT_DELAYED)
            return $this->redirectToFinish($order->getTemporaryId());

        // redirect customer to shopping basket after failed payment
        if ($status == PaymentStatus::MOLLIE_PAYMENT_FAILED) {
            /** @var \MollieShopware\Components\Services\BasketService $basketService */
            $basketService = Shopware()->Container()
                ->get('mollie_shopware.basket_service');

            $basketService->restoreBasket($order);

            return $this->redirectBack('Payment failed');
        }

        // if payment canceled, expired or failed for unknown reasons, assign error to view
        if ($status == PaymentStatus::MOLLIE_PAYMENT_CANCELED)
            $this->view->assign('sMollieError', 'Payment canceled');
        elseif ($status == PaymentStatus::MOLLIE_PAYMENT_EXPIRED)
            $this->view->assign('sMollieError', 'Payment expired');
        else
            $this->view->assign('sMollieError', 'Payment failed');

        $this->view->addTemplateDir(__DIR__ . '/../../Resources/views');

        return false;
    }

    /**
     * Send a confirmation e-mail once the order is processed.
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @throws \Exception
     */
    private function sendConfirmationEmail($order)
    {
        /**
         * Create an instance of the core sOrder class. The sOrder
         * class is used to send the confirmation e-mail.
         */
        $sOrder = Shopware()->Modules()->Order();

        /** @var \MollieShopware\Models\Transaction $transaction */
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