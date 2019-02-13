<?php

// Mollie Shopware Plugin Version: 1.4

namespace MollieShopware\Components\Services;

use MollieShopware\Components\Logger;
use MollieShopware\Components\Constants\PaymentStatus;
use Shopware\Models\Order\Status;
use Symfony\Component\HttpFoundation\Session\Session;

class PaymentService
{
    /**
     * @var \MollieShopware\Components\MollieApiFactory|null $apiFactory
     */
    private $apiFactory = null;

    /**
     * @var \Mollie\Api\MollieApiClient|null $apiClient
     */
    private $apiClient = null;

    /**
     * @var \MollieShopware\Components\Config|null $config
     */
    private $config = null;

    /**
     * @var \Enlight_Components_Session_Namespace|null $session
     */
    private $session = null;

    /**
     * @var array|null
     */
    private $customEnvironmentVariables = null;

    /**
     * PaymentService constructor
     *
     * @param \MollieShopware\Components\MollieApiFactory $apiFactory
     * @param \MollieShopware\Components\Config $config
     * @param \Enlight_Components_Session_Namespace $session
     * @param array $customEnvironmentVariables
     */
    public function __construct($apiFactory, $config, $session, $customEnvironmentVariables) {
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
     */
    public function createTransaction()
    {
        /**
         * Create an instance of the TransactionRepository. The TransactionRepository is used to
         * get transactions from the database.
         *
         * @var \MollieShopware\Models\TransactionRepository $transactionRepo
         */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(
            \MollieShopware\Models\Transaction::class
        );

        /**
         * Create a new transaction and return it.
         */
        return $transactionRepo->create(null, null, null);
    }

    /**
     * Start the transaction
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \MollieShopware\Models\Transaction $transaction
     * @param array $orderDetails
     * @return null|string
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startTransaction($order, $transaction, $orderDetails = array())
    {
        // vars
        $checkoutUrl = '';
        $mollieOrder = null;
        $molliePayment = null;

        // get payment method
        $paymentMethod = $order->getPayment()->getName();

        if (strstr($paymentMethod, 'klarna') ||
            $this->config->useOrdersApiOnlyWhereMandatory() == false) {
            // prepare the order for mollie
            $mollieOrderPrepared = $this->prepareOrder($order, $orderDetails);

            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->apiClient->orders->create($mollieOrderPrepared);

            /** @var \MollieShopware\Models\OrderLinesRepository $orderLinesRepo */
            $orderLinesRepo = Shopware()->container()->get('models')
                ->getRepository('\MollieShopware\Models\OrderLines');

            // iterate over lines
            foreach($mollieOrder->lines as $index => $line) {
                // create new item
                $item = new \MollieShopware\Models\OrderLines();

                // set vars
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

        // set transaction vars
        $transaction->setOrderId($order->getId());

        if (!is_null($mollieOrder)) {
            $transaction->setMollieId($mollieOrder->id);
            $checkoutUrl = $mollieOrder->getCheckoutUrl();
        }

        if (!is_null($molliePayment)) {
            $transaction->setMolliePaymentId(($molliePayment->id));
            $checkoutUrl = $molliePayment->getCheckoutUrl();
        }

        // save transaction
        $transactionRepo->save($transaction);

        return $checkoutUrl;
    }

    /**
     * Ship the order
     * @param \Shopware\Models\Order\Order $order
     * @param $mollieId
     * @return bool|\Mollie\Api\Resources\Shipment|null
     * @throws \Exception
     */
    public function sendOrder($order, $mollieId)
    {
        // create mollie order object
        $mollieOrder = null;

        try {
            $mollieOrder = $this->apiClient->orders->get($mollieId);
        }
        catch (\Exception $ex) {
            throw new \Exception('The order could not be found at Mollie.');
        }

        // ship the order
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
     * Get the Mollie order object
     *
     * @param \Shopware\Models\Order\Order $order
     * @return \Mollie\Api\Resources\Order
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMollieOrder($order)
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
     * @return \Mollie\Api\Resources\Payment
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMolliePayment($order)
    {
        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(
            \MollieShopware\Models\Transaction::class
        );

        /** @var \MollieShopware\Models\Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        /** @var \Mollie\Api\Resources\Payment $molliePayment */
        $molliePayment = $this->apiClient->payments->get(
            $transaction->getMolliePaymentId()
        );

        return $molliePayment;
    }

    /**
     * Prepare the order for Mollie
     *
     * @param \Shopware\Models\Order\Order $order
     * @param array $orderDetails
     * @return array
     * @throws \Exception
     */
    private function prepareOrder($order, $orderDetails = array())
    {
        // vars
        $paymentParameters = [];
        $paymentMethod = $order->getPayment()->getName();
        $orderLines = $this->getOrderlines($order, $orderDetails);

        // prepare the redirect URLs
        $paymentWebhookUrl = $this->prepareRedirectUrl($order, 'notify', 'payment');
        $orderRedirectUrl = $this->prepareRedirectUrl($order, 'return');
        $orderWebhookUrl = $this->prepareRedirectUrl($order, 'notify');

        // remove mollie_ from payment method
        if (substr($paymentMethod, 0, 7) === 'mollie_'){
            $paymentMethod = substr($paymentMethod, 7);
        }

        if (strtolower($paymentMethod == 'ideal')) {
            $paymentParameters = [
                'issuer' => $this->getIdealIssuer(),
                'webhookUrl' => $paymentWebhookUrl,
            ];
        }

        // create prepared order array
        $molliePrepared = [
            'amount' => $this->getPrice($order, round($order->getInvoiceAmount(), 2)),
            'orderNumber' => $order->getNumber(),
            'lines' => $orderLines,
            'billingAddress' => $this->getAddress($order, 'billing'),
            'shippingAddress' => $this->getAddress($order, 'shipping'),
            'redirectUrl' => $orderRedirectUrl,
            'webhookUrl' => $orderWebhookUrl,
            'locale' => $this->getLocale(),
            'method' => $paymentMethod,
            'payment' => $paymentParameters,
            'metadata' => [],
        ];

        return $molliePrepared;
    }

    /**
     * Prepare the payment for Mollie
     *
     * @param \Shopware\Models\Order\Order $order
     * @param array $orderDetails
     * @return array
     * @throws \Exception
     */
    private function preparePayment($order)
    {
        // vars
        $paymentMethod = $order->getPayment()->getName();

        // prepare the redirect URLs
        $paymentWebhookUrl = $this->prepareRedirectUrl($order, 'notify', 'payment');
        $paymentRedirectUrl = $this->prepareRedirectUrl($order, 'return', 'payment');

        // remove mollie_ from payment method
        if (substr($paymentMethod, 0, 7) === 'mollie_'){
            $paymentMethod = substr($paymentMethod, 7);
        }

        // create prepared order array
        $molliePrepared = [
            'amount' => $this->getPrice($order, round($order->getInvoiceAmount(), 2)),
            'description' => 'Order ' . $order->getNumber(),
            'method' => $paymentMethod,
            'redirectUrl' => $paymentRedirectUrl,
            'webhookUrl' => $paymentWebhookUrl,
            'locale' => $this->getLocale(),
        ];

        // get issuer
        if (strtolower($paymentMethod == 'ideal'))
            $molliePrepared['issuer'] = $this->getIdealIssuer();

        return $molliePrepared;
    }

    /**
     * @param \Shopware\Models\Order\Order $order
     * @return array
     */
    private function getOrderlines($order, $orderDetails = array())
    {
        // vars
        $items = [];
        $invoiceShippingTaxRate = null;

        // get invoice shipping tax rate
        if (method_exists($order, 'getInvoiceShippingTaxRate')) {
            $invoiceShippingTaxRate = $order->getInvoiceShippingTaxRate();
        }
        else {
            $invoiceShippingTaxRate = $this->getInvoiceShippingTaxRate($order);
        }

        // iterate over order details data
        foreach($orderDetails as $orderDetail)
        {
            // add detail to items
            $items[] = [
                'type' => $orderDetail['type'],
                'name' => $orderDetail['name'],
                'quantity' => (int)$orderDetail['quantity'],
                'unitPrice' => $this->getPrice($order, $orderDetail['unit_price']),
                'totalAmount' => $this->getPrice($order, $orderDetail['total_amount']),
                'vatRate' => number_format($orderDetail['vat_rate'], 2, '.', ''),
                'vatAmount' => $this->getPrice($order, $orderDetail['vat_amount']),
                'sku' => null,
                'imageUrl' => null,
                'productUrl' => null,
            ];
        }

        // get shipping unit price
        $shippingUnitPrice = $order->getInvoiceShipping();

        // get shipping net price
        $shippingNetPrice = $order->getInvoiceShippingNet();

        // get shipping vat amount
        $shippingVatAmount = $shippingUnitPrice - $shippingNetPrice;

        // clear tax if order is tax free
        if ($order->getTaxFree()) {
            $shippingVatAmount = 0;
            $shippingUnitPrice = $shippingNetPrice;
        }

        // add shipping costs to items
        $items[] = [
            'type' => 'shipping_fee',
            'name' => 'Shipping fee',
            'quantity' => 1,
            'unitPrice' => $this->getPrice($order, $shippingUnitPrice),
            'totalAmount' => $this->getPrice($order, $shippingUnitPrice),
            'vatRate' => number_format($shippingVatAmount == 0 ? 0 : $invoiceShippingTaxRate, 2, '.', ''),
            'vatAmount' => $this->getPrice($order, $shippingVatAmount),
        ];

        return $items;
    }

    /**
     * Get the invoice shipping taxrate
     *
     * @param \Shopware\Models\Order\Order $order
     * @return float|int
     */
    private function getInvoiceShippingTaxRate($order)
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
     * Get price in Mollie's format
     *
     * @param \Shopware\Models\Order\Order $order
     * @param $amount
     * @return array
     */
    private function getPrice($order, $amount, $decimals = 2)
    {
        // return an array with currency and value

        return [
            'currency' => $order->getCurrency(),
            'value' => number_format($amount, $decimals, '.', ''),
        ];
    }

    /**
     * Get the address in Mollie's format
     *
     * @param \Shopware\Models\Order\Order $order
     * @param string $type
     * @return array
     */
    private function getAddress($order, $type = 'billing')
    {
        if ($type === 'billing') {
            /** @var \Shopware\Models\Order\Billing $address */
            $address = $order->getBilling();
        }
        else{
            /** @var \Shopware\Models\Order\Shipping $address */
            $address = $order->getShipping();
        }

        // get the customer
        $customer = $order->getCustomer();

        // get the country
        $country = $address->getCountry();

        // return an array with address details
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
     * @param string $method
     * @param string $type
     * @return string
     * @throws \Exception
     */
    private function prepareRedirectUrl($order, $action = 'return', $type = 'order')
    {
        // check for errors
        if (!in_array($action, ['return', 'notify']))
            throw new \Exception('Cannot generate "' . $action . '" url as method is undefined');
        if (!in_array($type, ['order', 'payment']))
            throw new \Exception('Cannot generate "' . $action . '" url as type is undefined');

        // generate redirect url
        $url = Shopware()->Front()->Router()->assemble([
            'controller'    => 'Mollie',
            'action'        => $action,
            'type'          => $type,
            'forceSecure'   => true,
            'appendSession' => true,
            'orderNumber'   => $order->getNumber(),
        ]);

        // check if we are on local development
        $mollieLocalDevelopment = false;

        if (isset($this->customEnvironmentVariables['mollieLocalDevelopment'])) {
            $mollieLocalDevelopment = $this->customEnvironmentVariables['mollieLocalDevelopment'];
        }

        // if we are on local development, reroute to a feedback URL
        if ($mollieLocalDevelopment == true) {
            return 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($url);
        }

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
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function checkOrderStatus($order)
    {
        // get mollie payment
        $mollieOrder = $this->getMollieOrder($order);

        // set the status
        if ($mollieOrder->isPaid())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID, true);
        elseif ($mollieOrder->isAuthorized())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, true);
        elseif ($mollieOrder->isCanceled())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, true, 'order');
        elseif ($mollieOrder->isCompleted())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_COMPLETED, true, 'order');

        return false;
    }

    /**
     * Check the payment status
     *
     * @param \Shopware\Models\Order\Order $order
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function checkPaymentStatus($order)
    {
        // get mollie payment
        $molliePayment = $this->getMolliePayment($order);

        // set the status
        if ($molliePayment->isPaid())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID, true);
        elseif ($molliePayment->isPending())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_DELAYED, true);
        elseif ($molliePayment->isAuthorized())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, true);
        elseif ($molliePayment->isCanceled())
            $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, true);

        return $this->checkPaymentStatusForOrder($order, true);
    }


    /**
     * Check the payment status for order
     *
     * @param \Shopware\Models\Order\Order $order
     * @param boolean $returnResult
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return bool
     */
    public function checkPaymentStatusForOrder($order, $returnResult = false)
    {
        /** @var \Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = $this->getMollieorder($order);
        $paymentsResult = $this->getPaymentsResultForOrder($mollieOrder);

        if ($paymentsResult['total'] > 0) {
            // fully paid
            if ($paymentsResult['paid'] == $paymentsResult['total'])
                $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_PAID, $returnResult);

            // fully authorized
            if ($paymentsResult['authorized'] == $paymentsResult['total'])
                $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, $returnResult);

            // fully canceled
            if ($paymentsResult['canceled'] == $paymentsResult['total'])
                $this->setPaymentStatus($order, PaymentStatus::MOLLIE_PAYMENT_CANCELED, $returnResult);
        }

        if ($returnResult)
            return false;
    }

    /**
     * Check if the payments for an order failed
     *
     * @param \Shopware\Models\Order\Order $order
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function havePaymentsForOrderFailed($order)
    {
        /** @var \Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = $this->getMollieOrder($order);
        $paymentsResult = $this->getPaymentsResultForOrder($mollieOrder);

        // fully failed
        if ($paymentsResult['total'] > 0) {
            if ($paymentsResult['failed'] == $paymentsResult['total'])
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
    public function setPaymentStatus($order, $status, $returnResult = false, $type = 'payment')
    {
        // get the order module
        $sOrder = Shopware()->Modules()->Order();

        /**
         * The order is paid
         */
        if ($status == PaymentStatus::MOLLIE_PAYMENT_PAID) {
            if ($type == 'order') {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_COMPLETELY_PAID,
                    $this->config->sendStatusMail()
                );
            }

            if ($type == 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_COMPLETELY_PAID,
                    $this->config->sendStatusMail()
                );
            }

            if ($returnResult)
                return true;
        }

        /**
         * The order is authorized
         */
        if ($status == PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED) {
            if ($type == 'order') {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED,
                    $this->config->sendStatusMail()
                );
            }

            if ($type == 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED,
                    $this->config->sendStatusMail()
                );
            }

            if ($returnResult)
                return true;
        }

        /**
         * The order is canceled
         */
        if ($status == PaymentStatus::MOLLIE_PAYMENT_CANCELED) {
            if ($type == 'order') {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                    $this->config->sendStatusMail()
                );
            }

            if ($type == 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                    $this->config->sendStatusMail()
                );
            }

            if ($returnResult)
                return true;
        }
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
            'paid' => 0,
            'authorized' => 0,
            'pending' => 0,
            'canceled' => 0,
            'failed' => 0
        ];

        if (!empty($mollieOrder) && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
            /** @var \Mollie\Api\Resources\Payment[] $payments */
            $payments = $mollieOrder->payments();

            $paymentsResult['total'] = count($payments);

            foreach ($payments as $payment) {
                if ($payment->isPaid())
                    $paymentsResult['paid']++;
                if ($payment->isAuthorized())
                    $paymentsResult['authorized']++;
                if ($payment->isPending())
                    $paymentsResult['pending']++;
                if ($payment->isCanceled())
                    $paymentsResult['canceled']++;
                if ($payment->isFailed())
                    $paymentsResult['failed']++;
            }
        }

        return $paymentsResult;
    }
}