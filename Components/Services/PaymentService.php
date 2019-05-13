<?php

// Mollie Shopware Plugin Version: 1.4.4

namespace MollieShopware\Components\Services;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\PaymentStatus;
use Shopware\Models\Order\Status;

class PaymentService
{
    /** @var \MollieShopware\Components\MollieApiFactory $apiFactory */
    protected $apiFactory;

    /** @var \Mollie\Api\MollieApiClient $apiClient */
    protected $apiClient;

    /** @var \MollieShopware\Components\Config $config */
    protected $config;

    /** @var \Enlight_Components_Session_Namespace $session */
    protected $session;

    /** @var array */
    protected $customEnvironmentVariables;

    /**
     * PaymentService constructor
     *
     * @param \MollieShopware\Components\MollieApiFactory $apiFactory
     * @param \MollieShopware\Components\Config $config
     * @param \Enlight_Components_Session_Namespace $session
     * @param array $customEnvironmentVariables
     *
     * @throws \Exception
     */
    public function __construct(
        \MollieShopware\Components\MollieApiFactory $apiFactory,
        \MollieShopware\Components\Config $config,
        \Enlight_Components_Session_Namespace $session,
        array $customEnvironmentVariables
    )
    {
        $this->apiFactory = $apiFactory;
        $this->apiClient = $apiFactory->create();
        $this->config = $config;
        $this->session = $session;
        $this->customEnvironmentVariables = $customEnvironmentVariables;
    }

    /**
     * Create the transaction in the TransactionRepository.
     *
     * @return \MollieShopware\Models\Transaction
     *
     * @throws \Exception
     */
    public function createTransaction()
    {
        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(
            \MollieShopware\Models\Transaction::class
        );

        return $transactionRepo->create(null, null, null);
    }

    /**
     * Start the transaction
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Models\Transaction $transaction
     * @param array $orderDetails
     *
     * @return null|string
     *
     * @throws \Mollie\Api\Exceptions\ApiException|\Exception
     */
    public function startTransaction(
        $paymentMethod,
        \MollieShopware\Models\Transaction $transaction
    )
    {
        // variables
        $checkoutUrl = '';
        $mollieOrder = null;
        $molliePayment = null;
        $order = null;

        if (!empty($transaction->getOrderId())) {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = Shopware()->Models()->getRepository(
                \Shopware\Models\Order\Order::class
            );

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderRepo->find($transaction->getOrderId());
        }

        if (strstr($paymentMethod, 'klarna') ||
            $this->config->useOrdersApiOnlyWhereMandatory() == false) {

            // prepare the order for mollie
            $mollieOrderPrepared = $this->prepareRequest(
                $paymentMethod,
                $transaction,
                true
            );

            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->apiClient->orders->create(
                $mollieOrderPrepared
            );

            /** @var \MollieShopware\Models\OrderLinesRepository $orderLinesRepo */
            $orderLinesRepo = Shopware()->container()->get('models')
                ->getRepository('\MollieShopware\Models\OrderLines');

            foreach($mollieOrder->lines as $index => $line) {
                // create new item
                $item = new \MollieShopware\Models\OrderLines();

                // set variables
                if (!empty($order))
                    $item->setOrderId($order->getId());

                $item->setTransactionId($transaction->getTransactionId());
                $item->setMollieOrderlineId($line->id);

                // save item
                $orderLinesRepo->save($item);
            }
        }
        else {
            // prepare the payment for mollie
            $molliePaymentPrepared = $this->prepareRequest(
                $paymentMethod,
                $transaction,
                false
            );

            /** @var \Mollie\Api\Resources\Payment $molliePayment */
            $molliePayment = $this->apiClient->payments->create(
                $molliePaymentPrepared
            );
        }

        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')
            ->getRepository('\MollieShopware\Models\Transaction');

        if (!empty($order))
            $transaction->setOrderId($order->getId());

        if (!empty($mollieOrder)) {
            $transaction->setMollieId($mollieOrder->id);
            $checkoutUrl = $mollieOrder->getCheckoutUrl();
        }

        if (!empty($molliePayment)) {
            $transaction->setMolliePaymentId(($molliePayment->id));
            $checkoutUrl = $molliePayment->getCheckoutUrl();
        }

        $transactionRepo->save($transaction);

        return $checkoutUrl;
    }

    /**
     * Start the transaction
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Models\Transaction $transaction
     * @param array $orderDetails
     *
     * @return null|string
     *
     * @throws \Mollie\Api\Exceptions\ApiException|\Exception
     */
    public function startOrderTransaction(
        \Shopware\Models\Order\Order $order,
        \MollieShopware\Models\Transaction $transaction,
        $orderDetails = array())
    {
        // variables
        $checkoutUrl = '';
        $mollieOrder = null;
        $molliePayment = null;
        $paymentMethod = $order->getPayment()->getName();

        if (strstr($paymentMethod, 'klarna') ||
            $this->config->useOrdersApiOnlyWhereMandatory() == false) {

            // prepare the order for mollie
            $mollieOrderPrepared = $this->prepareOrder($order, $orderDetails);

            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->apiClient->orders->create(
                $mollieOrderPrepared
            );

            /** @var \MollieShopware\Models\OrderLinesRepository $orderLinesRepo */
            $orderLinesRepo = Shopware()->container()->get('models')
                ->getRepository('\MollieShopware\Models\OrderLines');

            foreach($mollieOrder->lines as $index => $line) {
                // create new item
                $item = new \MollieShopware\Models\OrderLines();

                // set variables
                $item->setOrderId($order->getId());
                $item->setMollieOrderlineId($line->id);

                // save item
                $orderLinesRepo->save($item);
            }
        }
        else {
            // prepare the payment for mollie
            $molliePaymentPrepared = $this->preparePayment($order);

            /** @var \Mollie\Api\Resources\Payment $molliePayment */
            $molliePayment = $this->apiClient->payments->create(
                $molliePaymentPrepared
            );
        }

        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')
            ->getRepository('\MollieShopware\Models\Transaction');

        $transaction->setOrderId($order->getId());

        if (!empty($mollieOrder)) {
            $transaction->setMollieId($mollieOrder->id);
            $checkoutUrl = $mollieOrder->getCheckoutUrl();
        }

        if (!empty($molliePayment)) {
            $transaction->setMolliePaymentId(($molliePayment->id));
            $checkoutUrl = $molliePayment->getCheckoutUrl();
        }

        $transactionRepo->save($transaction);

        return $checkoutUrl;
    }

    /**
     * Get the Mollie order object
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @return \Mollie\Api\Resources\Order
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMollieOrder(\Shopware\Models\Order\Order $order)
    {
        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(
            \MollieShopware\Models\Transaction::class
        );

        /** @var \MollieShopware\Models\Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        /** @var \Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = $this->apiClient->orders->get(
            $transaction->getMollieId(),
            [
                'embed' => 'payments'
            ]
        );

        return $mollieOrder;
    }

    /**
     * Get the Mollie payment object
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @return \Mollie\Api\Resources\Payment
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMolliePayment(\Shopware\Models\Order\Order $order, $paymentId = '')
    {
        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(
            \MollieShopware\Models\Transaction::class
        );

        /** @var \MollieShopware\Models\Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        if (empty($paymentId))
            $paymentId = $transaction->getMolliePaymentId();

        /** @var \Mollie\Api\Resources\Payment $molliePayment */
        $molliePayment = $this->apiClient->payments->get(
            $paymentId
        );

        return $molliePayment;
    }

    /**
     * Prepare the request for Mollie
     *
     * @param \MollieShopware\Models\Transaction $transaction
     * @param array $orderDetails
     *
     * @return array
     *
     * @throws \Exception
     */
    private function prepareRequest(
        $paymentMethod,
        \MollieShopware\Models\Transaction $transaction,
        $ordersApi = false
    )
    {
        // variables
        $molliePrepared = [];
        $basketData = Shopware()->Modules()->Basket()->sGetBasketData();
        $paymentParameters = [];

        // get webhook and redirect URLs
        $paymentRedirectUrl = $this->prepareRedirectUrl(
            $transaction->getOrderNumber() ?: $transaction->getTransactionId(),
            'return',
            'payment',
            !empty($transaction->getOrderNumber()) ? false : true
        );

        $paymentWebhookUrl = $this->prepareRedirectUrl(
            $transaction->getOrderNumber() ?: $transaction->getTransactionId(),
            'notify',
            'payment',
            !empty($transaction->getOrderNumber()) ? false : true
        );

        $orderRedirectUrl = $this->prepareRedirectUrl(
            $transaction->getOrderNumber() ?: $transaction->getTransactionId(),
            'return',
            'order',
            !empty($transaction->getOrderNumber()) ? false : true
        );

        $orderWebhookUrl = $this->prepareRedirectUrl(
            $transaction->getOrderNumber() ?: $transaction->getTransactionId(),
        'notify',
            'order',
            !empty($transaction->getOrderNumber()) ? false : true
        );

        $paymentParameters['webhookUrl'] = $paymentWebhookUrl;

        if (substr($paymentMethod, 0, strlen('mollie_')) == 'mollie_')
            $paymentMethod = substr($paymentMethod, strlen('mollie_'));

        // set method specific parameters
        $paymentParameters = $this->preparePaymentParameters(
            $paymentMethod,
            $paymentParameters
        );

        // create prepared order array
        $molliePrepared = [
            'amount' => $this->getPriceArray(
                $transaction->getCurrency(),
                round($transaction->getTotalAmount(), 2)
            ),
            'redirectUrl' => $paymentRedirectUrl,
            'webhookUrl' => $paymentWebhookUrl,
            'locale' => $transaction->getLocale(),
            'method' => $paymentMethod,
            'payment' => $paymentParameters,
        ];

        // add extra parameters depending on using the Orders API or the Payments API
        if ($ordersApi) {
            // get order lines
            $orderLines = $this->getOrderlines($transaction);

            // set order parameters
            $molliePrepared['orderNumber'] = strval($transaction->getOrderNumber());

            if (empty($molliePrepared['orderNumber']))
                $molliePrepared['orderNumber'] = strval($transaction->getTransactionId());

            $molliePrepared['lines'] = $orderLines;
            $molliePrepared['billingAddress'] = $this->getAddress(
                $transaction->getCustomer()->getDefaultBillingAddress(),
                $transaction->getCustomer()
            );
            $molliePrepared['shippingAddress'] = $this->getAddress(
                $transaction->getCustomer()->getDefaultShippingAddress(),
                $transaction->getCustomer()
            );
            $molliePrepared['redirectUrl'] = $orderRedirectUrl;
            $molliePrepared['webhookUrl'] = $orderWebhookUrl;
            $molliePrepared['metadata'] = [];
        } else {
            // add description
            $molliePrepared['description'] = !empty($transaction->getOrderNumber()) ? 'Order ' . $transaction->getOrderNumber() : 'Transaction ' . $transaction->getTransactionId();

            // add billing e-mail address
            if ($paymentMethod == PaymentMethod::BANKTRANSFER || $paymentMethod == PaymentMethod::P24)
                $molliePrepared['billingEmail'] = $transaction->getCustomer()->getEmail();

            // prepare payment parameters
            $molliePrepared = $this->preparePaymentParameters(
                $paymentMethod,
                $molliePrepared
            );
        }

        return $molliePrepared;
    }

    /**
     * Get the order lines for an order
     *
     * @param \MollieShopware\Models\Transaction $transaction
     *
     * @return array
     */
    private function getOrderlines(\MollieShopware\Models\Transaction $transaction)
    {
        $orderlines = [];
        $invoiceShippingTaxRate = null;

//        if (method_exists($order, 'getInvoiceShippingTaxRate'))
//            $invoiceShippingTaxRate = $order->getInvoiceShippingTaxRate();
//        else
//            $invoiceShippingTaxRate = $this->getInvoiceShippingTaxRate($order);

        /** @var \MollieShopware\Models\TransactionItem $item */
        foreach($transaction->getItems() as $item)
        {
            $orderlines[] = [
                'type' => $item->getType(),
                'name' => $item->getName(),
                'quantity' => (int)$item->getQuantity(),
                'unitPrice' => $this->getPriceArray($transaction->getCurrency(), $item->getUnitPrice()),
                'totalAmount' => $this->getPriceArray($transaction->getCurrency(), $item->getTotalAmount()),
                'vatRate' => number_format($item->getVatRate(), 2, '.', ''),
                'vatAmount' => $this->getPriceArray($transaction->getCurrency(), $item->getVatAmount()),
                'sku' => null,
                'imageUrl' => null,
                'productUrl' => null,
            ];
        }

        // @todo Add shipping costs

//        // get shipping amount
//        $shippingUnitPrice = $order->getInvoiceShipping();
//        $shippingNetPrice = $order->getInvoiceShippingNet();
//        $shippingVatAmount = $shippingUnitPrice - $shippingNetPrice;
//
//        // clear shipping tax if order is tax free
//        if ($order->getTaxFree()) {
//            $shippingVatAmount = 0;
//            $shippingUnitPrice = $shippingNetPrice;
//        }
//
//        // add shipping costs to items
//        $orderlines[] = [
//            'type' => 'shipping_fee',
//            'name' => 'Shipping fee',
//            'quantity' => 1,
//            'unitPrice' => $this->getPriceArray($order->getCurrency(), $shippingUnitPrice),
//            'totalAmount' => $this->getPriceArray($order->getCurrency(), $shippingUnitPrice),
//            'vatRate' => number_format($shippingVatAmount == 0 ? 0 : $invoiceShippingTaxRate, 2, '.', ''),
//            'vatAmount' => $this->getPriceArray($order->getCurrency(), $shippingVatAmount),
//        ];

        return $orderlines;
    }

    /**
     * Get the invoice shipping taxrate
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @return float|int
     */
    private function getInvoiceShippingTaxRate(\Shopware\Models\Order\Order $order)
    {
        // vars
        $invoiceShippingGross = $order->getInvoiceShipping();
        $invoiceShippingNet = $order->getInvoiceShippingNet();

        // no tax
        if ($invoiceShippingGross === $invoiceShippingNet)
            return 0;

        // get tax amount
        $invoiceShippingTaxAmount = $invoiceShippingGross - $invoiceShippingNet;
        $invoiceShippingTaxRate = round((($invoiceShippingTaxAmount / $invoiceShippingNet) * 100) * 2) / 2;

        return $invoiceShippingTaxRate;
    }

    /**
     * Get price in currency/value array
     *
     * @param $currency
     * @param $amount
     * @param int $decimals
     *
     * @return array
     */
    private function getPriceArray($currency, $amount, $decimals = 2)
    {
        return [
            'currency' => $currency,
            'value' => number_format($amount, $decimals, '.', ''),
        ];
    }

    /**
     * Get the address in array
     *
     * @param \Shopware\Models\Customer\Address | \Shopware\Models\Order\Billing | \Shopware\Models\Order\Shipping $address
     * @param string $type
     *
     * @return array
     */
    private function getAddress(
        $address,
        \Shopware\Models\Customer\Customer $customer
    )
    {
        $country = $address->getCountry();

        return [
            'title' => $address->getSalutation() . '.',
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $customer->getEmail(),
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $country ? $country->getIso() : 'NL',
        ];
    }

    /**
     * Prepare the redirect URL for Mollie
     *
     * @param \Shopware\Models\Order\Order $order
     * @param string $action
     * @param string $type
     *
     * @return string
     *
     * @throws \Exception
     */
    private function prepareRedirectUrl($number, $action = 'return', $type = 'order', $isTemporary = false)
    {
        // check for errors
        if (!in_array($action, ['return', 'notify']))
            throw new \Exception('Cannot generate "' . $action . '" url as method is undefined');
        if (!in_array($type, ['order', 'payment']))
            throw new \Exception('Cannot generate "' . $action . '" url as type is undefined');

        // generate redirect url
        $assembleData = [
            'controller'    => 'Mollie',
            'action'        => $action,
            'type'          => $type,
            'forceSecure'   => true
        ];

        if (!$isTemporary)
            $assembleData['orderNumber'] = $number;
        else
            $assembleData['transactionNumber'] = $number;

//        if ($action == 'return')
//            $assembleData['appendSession'] = true;

        $url = Shopware()->Front()->Router()->assemble($assembleData);

        // check if we are on local development
        $mollieLocalDevelopment = false;

        if (isset($this->customEnvironmentVariables['mollieLocalDevelopment']))
            $mollieLocalDevelopment = $this->customEnvironmentVariables['mollieLocalDevelopment'];

        if ($mollieLocalDevelopment == true)
            return 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($url);

        return $url;
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
     * Get the id of the chosen ideal issuer from database
     *
     * @return string
     */
    protected function getIdealIssuer()
    {
        /** @var \MollieShopware\Components\Services\IdealService $idealService */
        $idealService = Shopware()->container()->get('mollie_shopware.ideal_service');

        return $idealService->getSelectedIssuer();
    }

    /**
     * Check the payment status
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @return bool
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function checkOrderStatus(\Shopware\Models\Order\Order $order)
    {
        // get mollie payment
        $mollieOrder = $this->getMollieOrder($order);

        // set the status
        if ($mollieOrder->isPaid())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID, true);
        elseif ($mollieOrder->isAuthorized())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, true);
        elseif ($mollieOrder->isCanceled())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, true, 'order');
        elseif ($mollieOrder->isCompleted())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_COMPLETED, true, 'order');

        return false;
    }

    /**
     * Check the payment status
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @return bool
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function checkPaymentStatus(\Shopware\Models\Order\Order $order, $paymentId = '')
    {
        // get mollie payment
        $molliePayment = $this->getMolliePayment($order, $paymentId);

        // set the status
        if ($molliePayment->isPaid())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID, true);
        elseif ($molliePayment->isPending())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED, true);
        elseif ($molliePayment->isAuthorized())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, true);
        elseif ($molliePayment->isOpen())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_OPEN, true);
        elseif ($molliePayment->isCanceled())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, true);
        elseif ($molliePayment->isExpired())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_EXPIRED, true);
        elseif ($molliePayment->isFailed())
            return $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_FAILED, true);

        return $this->checkPaymentStatusForOrder($order, true);
    }


    /**
     * Check the payment status for order
     *
     * @param \Shopware\Models\Order\Order $order
     * @param boolean $returnResult
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function checkPaymentStatusForOrder(\Shopware\Models\Order\Order $order, $returnResult = false)
    {
        /** @var \Mollie\Api\Resources\Order $mollieOrder */
        try {
            $mollieOrder = $this->getMollieOrder($order);
        }
        catch (\Exception $ex) {
            //
        }

        if (!empty($mollieOrder)) {
            $paymentsResult = $this->getPaymentsResultForOrder($mollieOrder);

            if ($paymentsResult['total'] > 0) {
                // fully paid
                if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_PAID] == $paymentsResult['total']) {
                    $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID, $returnResult);

                    if ($returnResult)
                        return true;
                }

                // fully delayed
                if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_DELAYED] == $paymentsResult['total']) {
                    $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED, $returnResult);

                    if ($returnResult)
                        return true;
                }

                // fully authorized
                if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED] == $paymentsResult['total']) {
                    $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, $returnResult);

                    if ($returnResult)
                        return true;
                }

                // fully canceled
                if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_CANCELED] == $paymentsResult['total']) {
                    $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, $returnResult);

                    if ($returnResult)
                        return true;
                }

                // fully open
                if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_OPEN] == $paymentsResult['total']) {
                    $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_OPEN, $returnResult);

                    if ($returnResult)
                        return true;
                }
            }

            if ($returnResult)
                return false;
        }

        if ($returnResult)
            return true;
    }

    /**
     * Check if the payments for an order failed
     *
     * @param \Shopware\Models\Order\Order $order
     * @param string $status
     *
     * @return bool
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function isOrderPaymentsStatus(\Shopware\Models\Order\Order $order, $status)
    {
        /** @var \Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = $this->getMollieOrder($order);
        $paymentsResult = $this->getPaymentsResultForOrder($mollieOrder);

        // fully failed
        if ($paymentsResult['total'] > 0) {
            if ($paymentsResult[$status] == $paymentsResult['total'])
                return true;
        }

        return false;
    }

    /**
     * Check the order status and redirect the user if possible
     * also, if the payment is complete or authorized, send the confirmation e-mail
     *
     * @param \Shopware\Models\Order\Order $order
     * @param string $status
     * @param boolean $returnResult
     * @throws \Exception
     * @return mixed
     */
    public function setPaymentStatus(\Shopware\Models\Order\Order $order, $status, $returnResult = false, $type = 'payment')
    {
        // get the order module
        $sOrder = Shopware()->Modules()->Order();

        // the order is completed
        if ($status == PaymentStatus::MOLLIE_PAYMENT_COMPLETED) {
            if ($type == 'order' && $this->config->updateOrderStatus()) {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_COMPLETED,
                    $this->config->sendStatusMail()
                );
            }

            if ($returnResult)
                return true;
        }

        // the order or payment is paid
        if ($status == PaymentStatus::MOLLIE_PAYMENT_PAID) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_COMPLETELY_PAID,
                $this->config->sendStatusMail()
            );

            if ($returnResult)
                return true;
        }

        // the order or payment is authorized
        if ($status == PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                $this->config->getAuthorizedPaymentStatusId(),
                $this->config->sendStatusMail()
            );

            if ($returnResult)
                return true;
        }

        // the payment is delayed
        if ($status == PaymentStatus::MOLLIE_PAYMENT_DELAYED) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_DELAYED,
                $this->config->sendStatusMail()
            );

            if ($returnResult)
                return true;
        }

        // the payment is open
        if ($status == PaymentStatus::MOLLIE_PAYMENT_OPEN) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_OPEN,
                $this->config->sendStatusMail()
            );

            if ($returnResult)
                return true;
        }

        // the order or payment is canceled
        if ($status == PaymentStatus::MOLLIE_PAYMENT_CANCELED) {
            if ($type == 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                    $this->config->sendStatusMail()
                );
            }

            if ($this->config->cancelFailedOrders() || ($type == 'order' && $this->config->updateOrderStatus())) {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_CANCELLED_REJECTED,
                    $this->config->sendStatusMail()
                );
            }

            if ($returnResult)
                return true;
        }

        // the payment has failed or is expired
        if ($status == PaymentStatus::MOLLIE_PAYMENT_FAILED ||
            $status == PaymentStatus::MOLLIE_PAYMENT_EXPIRED) {
            if ($type == 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                    $this->config->sendStatusMail()
                );
            }

            if ($this->config->cancelFailedOrders()) {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_CANCELLED_REJECTED,
                    $this->config->sendStatusMail()
                );
            }

            if ($returnResult)
                return true;
        }
    }

    /**
     * Ship the order
     *
     * @param string $mollieId
     *
     * @return bool|\Mollie\Api\Resources\Shipment|null
     *
     * @throws \Exception
     */
    public function sendOrder($mollieId)
    {
        $mollieOrder = null;

        try {
            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->apiClient->orders->get($mollieId);
        }
        catch (\Exception $ex) {
            throw new \Exception('Order ' . $mollieId . ' could not be found at Mollie.');
        }

        if (!empty($mollieOrder)) {
            $result = null;

            if (!$mollieOrder->isPaid() && !$mollieOrder->isAuthorized()) {
                if ($mollieOrder->isCompleted()) {
                    throw new \Exception('The order is already completed at Mollie.');
                }
                else {
                    throw new \Exception('The order doesn\'t seem to be paid or authorized.');
                }
            }

            try {
                $result = $mollieOrder->shipAll();
            }
            catch (\Exception $ex) {
                throw new \Exception('The order can\'t be shipped.');
            }

            return $result;
        }

        return false;
    }

    /**
     * Prepare the payment parameters based on the payment method's requirements
     *
     * @param $paymentMethod
     * @param array $paymentParameters
     *
     * @return array
     */
    private function preparePaymentParameters(
        $paymentMethod,
        array $paymentParameters)
    {
        if ($paymentMethod == PaymentMethod::IDEAL)
            $paymentParameters['issuer'] = $this->getIdealIssuer();

        return $paymentParameters;
    }

    /**
     * Retrieve payments result for order
     *
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return array
     */
    private function getPaymentsResultForOrder($mollieOrder = null)
    {
        $paymentsResult = [
            'total' => 0,
            PaymentStatus::MOLLIE_PAYMENT_PAID => 0,
            PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED => 0,
            PaymentStatus::MOLLIE_PAYMENT_DELAYED => 0,
            PaymentStatus::MOLLIE_PAYMENT_OPEN => 0,
            PaymentStatus::MOLLIE_PAYMENT_CANCELED => 0,
            PaymentStatus::MOLLIE_PAYMENT_FAILED => 0,
            PaymentStatus::MOLLIE_PAYMENT_EXPIRED => 0
        ];

        if (!empty($mollieOrder) && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
            /** @var \Mollie\Api\Resources\Payment[] $payments */
            $payments = $mollieOrder->payments();

            $paymentsResult['total'] = count($payments);

            foreach ($payments as $payment) {
                if ($payment->isPaid())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_PAID]++;
                if ($payment->isAuthorized())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED]++;
                if ($payment->isPending())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_DELAYED]++;
                if ($payment->isOpen())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_OPEN]++;
                if ($payment->isCanceled())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_CANCELED]++;
                if ($payment->isFailed())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_FAILED]++;
                if ($payment->isExpired())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_EXPIRED]++;
            }
        }

        return $paymentsResult;
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
}