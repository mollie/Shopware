<?php

// Mollie Shopware Plugin Version: 1.4.4

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
        // check if basket exists
        if (!Shopware()->Modules()->Basket()->sCountBasket())
            return $this->redirectBack();

        // variables
        $orderNumber = null;
        $orderDetails = null;

        /** @var \MollieShopware\Components\Config $config */
        $config = Shopware()->Container()->get('mollie_shopware.config');

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

        // prepare transaction
        $transaction = $this->prepareTransaction($transaction, $signature);

        // store the transaction
        Shopware()->Models()->persist($transaction);
        Shopware()->Models()->flush();

        if ($config->createOrderBeforePayment()) {
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

            // Store order number on transaction
            $transaction->setOrderNumber($orderNumber);

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
             * @var \Shopware\Models\Order\Order $order
             */
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
            } else {
                // store order id on transaction
                $transaction->setOrderId($order->getId());
            }
        }

        return $this->redirect(
            $paymentService->startTransaction(
                $this->getPaymentShortName(),
                $transaction
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

        /** @var string $transactionNumber */
        $transactionNumber = $this->Request()->getParam('transactionNumber');

        try {
            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->getOrder();

            if (empty($order) || $order == false || !$order instanceof \Shopware\Models\Order\Order) {
                if (!empty($transactionNumber)) {
                    $order = $this->getOrderFromTransaction($transactionNumber);
                }
            }
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
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

        // something went wrong because nothing is returned until now
        Logger::log(
            'error',
            'The order couldn\'t be retrieved.',
            null,
            true
        );
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

            if (empty($order) || $order == false || !$order instanceof \Shopware\Models\Order\Order) {
                if (!empty($transactionNumber)) {
                    $order = $this->getOrderFromTransaction($transactionNumber);
                }
            }
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
                Notifier::notifyOk(
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
        try {
            $orderNumber = $this->Request()->getParam('orderNumber');

            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $orderService = Shopware()->Container()
                ->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderService->getOrderByNumber($orderNumber);

            if (!empty($order)) {
                $this->retryOrderRestore($order);
            }
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $this->redirectBack();
    }

    private function prepareTransaction(\MollieShopware\Models\Transaction $transaction, $basketSignature)
    {
        try {
            // variables
            $transactionItems = new \Doctrine\Common\Collections\ArrayCollection();

            // get currency
            $currency = method_exists($this, 'getCurrencyShortName') ? $this->getCurrencyShortName() : 'EUR';

            // get customer
            $customer = $this->getCurrentCustomer();

            // build transaction
            $transaction->setBasketSignature($basketSignature);
            $transaction->setLocale($this->getLocale());
            $transaction->setCurrency($currency);
            $transaction->setTotalAmount($this->getAmount());

            // set transaction as net order
            if (isset($this->getUser()['additional']) &&
                (!isset($this->getUser()['additional']['show_net']) ||
                    empty($this->getUser()['additional']['show_net']))
            ) {
                $transaction->setNet(true);
            }

            // set transaction as tax free
            if (isset($this->getUser()['additional']) &&
                (!isset($this->getUser()['additional']['charge_vat']) ||
                    empty($this->getUser()['additional']['charge_vat']))
            ) {
                $transaction->setTaxFree(true);
            }

            // set the customer
            if (!empty($customer)) {
                $transaction->setCustomer($customer);
                $transaction->setCustomerId($customer->getId());
            }

            /** @var \MollieShopware\Components\Services\BasketService $basketService */
            $basketService = Shopware()->Container()
                ->get('mollie_shopware.basket_service');

            foreach ($basketService->getBasketLines($this->getUser()) as $basketLine) {
                // create transaction item
                $transactionItem = new \MollieShopware\Models\TransactionItem();

                // set transaction item variables
                $transactionItem->setTransaction($transaction);
                $transactionItem->setName($basketLine['name']);
                $transactionItem->setType($basketLine['type']);
                $transactionItem->setQuantity($basketLine['quantity']);
                $transactionItem->setUnitPrice($basketLine['unit_price']);
                $transactionItem->setNetPrice($basketLine['net_price']);
                $transactionItem->setTotalAmount($basketLine['total_amount']);
                $transactionItem->setVatRate($basketLine['vat_rate']);
                $transactionItem->setVatAmount($basketLine['vat_amount']);

                // add transaction item to collection
                $transactionItems->add($transactionItem);
            }

            // shipping costs
            $shippingCosts = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts();

            if (is_array($shippingCosts) && count($shippingCosts)) {
                // create shipping item
                $shippingItem = new \MollieShopware\Models\TransactionItem();

                // get shipping tax rate
                $shippingTaxRate = floatval($shippingCosts['tax']);

                // get shipping the unit price
                $shippingUnitPrice = round(floatval($shippingCosts['brutto']), 2);

                // get shipping net price
                $shippingNetPrice = floatval($shippingCosts['netto']);

                // clear shipping tax if order is tax free
                if ($transaction->getTaxFree() === true) {
                    $shippingUnitPrice = $shippingNetPrice;
                }

                // get shipping vat amount
                $shippingVatAmount = $shippingUnitPrice * ($shippingTaxRate / ($shippingTaxRate + 100));

                // clear shipping vat amount if order is tax free
                if ($transaction->getTaxFree() === true) {
                    $shippingVatAmount = 0;
                }

                // set shipping item variables
                $shippingItem->setTransaction($transaction);
                $shippingItem->setName('Shipping fee');
                $shippingItem->setType('shipping_fee');
                $shippingItem->setQuantity(1);
                $shippingItem->setUnitPrice($shippingUnitPrice);
                $shippingItem->setNetPrice($shippingNetPrice);
                $shippingItem->setTotalAmount($shippingUnitPrice);
                $shippingItem->setVatRate($shippingVatAmount == 0 ? 0 : $shippingTaxRate);
                $shippingItem->setVatAmount($shippingVatAmount);

                // add shipping item to collection
                $transactionItems->add($shippingItem);
            }

            // set transactions items
            if ($transactionItems->count())
                $transaction->setItems($transactionItems);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $transaction;
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

    private function getOrderFromTransaction($transactionNumber)
    {
        $order = null;

        try {
            /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
            $transactionRepo = Shopware()->Models()->getRepository(
                \MollieShopware\Models\Transaction::class
            );

            /** @var \MollieShopware\Models\Transaction $transaction */
            $transaction = $transactionRepo->findOneBy([
                'transactionId' => $transactionNumber
            ]);

            if (!empty($transaction)) {
                // get the order number
                $orderNumber = $transaction->getOrderNumber();

                // get the transaction ID
                $transactionId = $transaction->getMolliePaymentId();

                if (empty($transactionId))
                    $transactionId = $transaction->getMollieId();

                if (empty($transactionId))
                    $transactionId = $transaction->getTransactionId();

                // if order doesn't exist, save the order and retrieve an order number
                if (empty($orderNumber)) {
                    $orderNumber = $this->saveOrder(
                        $transactionId,
                        $transaction->getBasketSignature(),
                        Status::PAYMENT_STATE_OPEN,
                        false
                    );

                    // update the order number at Mollie
                    $this->updateMollieOrderNumber($transaction);
                }

                /** @var \MollieShopware\Components\Services\OrderService $orderService */
                $orderService = Shopware()->Container()
                    ->get('mollie_shopware.order_service');

                /** @var \Shopware\Models\Order\Order $order */
                $order = $orderService->getOrderByNumber($orderNumber);

                if (!empty($order)) {
                    $transaction->setOrderNumber($order->getNumber());
                    $transaction->setOrderId($order->getId());

                    Shopware()->Models()->persist($transaction);
                    Shopware()->Models()->flush();
                }
            }
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
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
     * Get the locale for this payment
     *
     * @return string
     */
    private function getLocale()
    {
        // mollie locales
        $mollieLocales = [
            'en_US',
            'nl_NL',
            'fr_FR',
            'it_IT',
            'de_DE',
            'de_AT',
            'de_CH',
            'es_ES',
            'ca_ES',
            'nb_NO',
            'pt_PT',
            'sv_SE',
            'fi_FI',
            'da_DK',
            'is_IS',
            'hu_HU',
            'pl_PL',
            'lv_LV',
            'lt_LT'
        ];

        // get shop locale
        $locale = Shopware()->Shop()->getLocale()->getLocale();

        // set default locale on empty or not supported shop locale
        if (empty($locale) || !in_array($locale, $mollieLocales))
            $locale = 'en_US';

        return $locale;
    }

    /**
     * Get the current customer
     *
     * @return \Shopware\Models\Customer\Customer|null
     */
    private function getCurrentCustomer()
    {
        $currentCustomer = null;

        try {
            $currentCustomerClass = new \MollieShopware\Components\CurrentCustomer(
                Shopware()->Session(),
                Shopware()->Models()
            );

            $currentCustomer = $currentCustomerClass->getCurrent();
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $currentCustomer;
    }

    private function updateMollieOrderNumber(
        \MollieShopware\Models\Transaction $transaction
    )
    {
        if (strlen($transaction->getMollieId())) {
            /** @var \Mollie\Api\MollieApiClient $mollieApi */
            $mollieApi = $this->container->get('mollie_shopware.api');

            try {
                /** @var \Mollie\Api\Resources\Order $mollieOrder */
                $mollieOrder = $mollieApi->orders->get($transaction->getMollieId());

                // set the new order number
                $mollieOrder->orderNumber = $transaction->getOrderNumber();

                // store the new order number
                $mollieOrder->update();
            }
            catch (\Exception $ex) {
                Logger::log(
                    'error',
                    $ex->getMessage(),
                    $ex
                );
            }
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

        $authorizedStatusId = Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED;

        if (defined('\Shopware\Models\Order\Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED'))
            $authorizedStatusId = Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED;

        // check the existing order status
        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_COMPLETELY_PAID)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_DELAYED)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED);

        if ($order->getPaymentStatus()->getId() == $authorizedStatusId)
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

        // check if order payments are delayed
        if ($paymentService->isOrderPaymentsStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED);

        // check if order payments are open
        if ($paymentService->isOrderPaymentsStatus($order, PaymentStatus::MOLLIE_PAYMENT_OPEN))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_OPEN);

        // check if order payments are canceled
        if ($paymentService->isOrderPaymentsStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED);

        // check if order payments are expired
        if ($paymentService->isOrderPaymentsStatus($order, PaymentStatus::MOLLIE_PAYMENT_EXPIRED))
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_EXPIRED);

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

        $authorizedStatusId = Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED;

        if (defined('\Shopware\Models\Order\Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED'))
            $authorizedStatusId = Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED;

        // check the existing order status
        if ($order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_COMPLETELY_PAID)
            return $this->processPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        if ($order->getPaymentStatus()->getId() == $authorizedStatusId)
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
            $this->retryOrderRestore($order);

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

    /**
     * Retry order restore
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @throws \Exception
     */
    private function retryOrderRestore(\Shopware\Models\Order\Order $order)
    {
        /** @var \MollieShopware\Components\CurrentCustomer $currentCustomer */
        $currentCustomer = new \MollieShopware\Components\CurrentCustomer(
            Shopware()->Session(),
            Shopware()->Models()
        );
        /** @var \DateTime $currentDateTime */
        $currentDateTime = new \DateTime(
            'now',
            $order->getOrderTime()->getTimezone()
        );

        /** @var \DateInterval $dateInterval */
        $dateInterval = $currentDateTime->diff(
            $order->getOrderTime()
        );

        $differenceInMinutes = $this->getDateIntervalTotalMinutes($dateInterval);

        if ($differenceInMinutes <= 10 &&
            $currentCustomer->getCurrentId() == $order->getCustomer()->getId()) {

            /** @var \MollieShopware\Components\Services\BasketService $basketService */
            $basketService = Shopware()->Container()
                ->get('mollie_shopware.basket_service');

            $basketService->restoreBasket($order);
        }
    }

    /**
     * Get total minutes from a DateInterval
     *
     * @param \DateInterval $int
     * @return float|int
     */
    private function getDateIntervalTotalMinutes(\DateInterval $int) {
        return ($int->d * 24 * 60) + ($int->h * 60) + $int->i;
    }
}