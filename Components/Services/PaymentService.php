<?php

// Mollie Shopware Plugin Version: 1.3.15

namespace MollieShopware\Components\Services;

use Shopware\Components\ConfigLoader;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
use Shopware\Models\Tax\Tax;
use Symfony\Component\HttpFoundation\Session\Session;
use Enlight_Components_Session;

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
     * @param \Enlight_Components_Session_Namespace $session
     * @param array $customEnvironmentVariables
     */
    public function __construct(
        \MollieShopware\Components\MollieApiFactory $apiFactory,
        \Enlight_Components_Session_Namespace $session,
        array $customEnvironmentVariables
    ) {
        $this->apiFactory = $apiFactory;
        $this->apiClient = $apiFactory->create();
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
        return $transactionRepo->create(null, null);
    }

    /**
     * Start the transaction
     *
     * @param Order $order
     * @param \MollieShopware\Models\Transaction $transaction
     * @param array $orderDetails
     * @return null|string
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startTransaction($order, $transaction, $orderDetails = array())
    {
        // prepare the order for mollie
        $molliePrepared = $this->prepareOrder($order, $orderDetails);

        /** @var \Mollie\Api\Resources\Payment $molliePayment */
        $molliePayment = $this->apiClient->orders->create($molliePrepared);

        /** @var \MollieShopware\Models\OrderLinesRepository $orderLinesRepo */
        $orderLinesRepo = Shopware()->container()->get('models')
            ->getRepository('\MollieShopware\Models\OrderLines');


        // iterate over lines
        foreach($molliePayment->lines as $index => $line) {
            // create new item
            $item = new \MollieShopware\Models\OrderLines();

            // set vars
            $item->setOrderId($order->getId());
            $item->setMollieOrderlineId($line->id);

            // save item
            $orderLinesRepo->save($item);
        }

        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')
            ->getRepository('\MollieShopware\Models\Transaction');

        // set transaction vars
        $transaction->setOrderId($order->getId());
        $transaction->setMollieId($molliePayment->id);

        // save transaction
        $transactionRepo->save($transaction);

        return $molliePayment->getCheckoutUrl();
    }

    /**
     * Ship the order
     * @param Order $order
     * @param $mollieId
     * @return bool|\Mollie\Api\Resources\Shipment|null
     * @throws \Exception
     */
    public function sendOrder(Order $order, $mollieId)
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
     * Get the payment object
     * @param Order $order
     * @return \Mollie\Api\Resources\Order
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getPaymentObject($order)
    {
        /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(Transaction::class);

        /** @var \MollieShopware\Models\Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        /** @var \Mollie\Api\Resources\Order $molliePayment */
        $molliePayment = $this->apiClient->orders->get($transaction->getMollieId());

        return $molliePayment;
    }

    /**
     * Prepare the order for Mollie
     *
     * @param Order $order
     * @param array $orderDetails
     * @return array
     * @throws Exception
     */
    private function prepareOrder($order, $orderDetails = array())
    {
        // vars
        $paymentParameters = [];
        $paymentMethod = $order->getPayment()->getName();
        $orderLines = $this->getOrderlines($order, $orderDetails);

        // remove mollie_ from payment method
        if (substr($paymentMethod, 0, 7) === 'mollie_'){
            $paymentMethod = substr($paymentMethod, 7);
        }

        if (strtolower($paymentMethod == 'ideal')) {
            $paymentParameters = [
                'customerId' => $order->getCustomer()->getNumber(),
                'issuer' => $this->getIdealIssuer(),
                'mandateId' => '',
                'webhookUrl' => $this->prepareRedirectUrl($order, 'webhook', 'payment'),
            ];
        }

        // create prepared order array
        $molliePrepared = [
            'amount' => $this->getPrice($order, round($order->getInvoiceAmount(), 2)),
            'orderNumber' => $order->getNumber(),
            'lines' => $orderLines,
            'billingAddress' => $this->getAddress($order, 'billing'),
            'shippingAddress' => $this->getAddress($order, 'shipping'),
            'redirectUrl' => $this->prepareRedirectUrl($order, 'redirect'),
            'webhookUrl' => $this->prepareRedirectUrl($order, 'webhook'),
            'locale' => $this->getLocale($order),
            'method' => $paymentMethod,
            'payment' => $paymentParameters,
            'metadata' => [],
        ];

        return $molliePrepared;
    }

    /**
     * @param Order $order
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
     * @param Order $order
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
     * @param Order $order
     * @param $amount
     * @return array
     */
    private function getPrice(Order $order, $amount, $decimals = 2)
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
     * @param Order $order
     * @param string $type
     * @return array
     */
    private function getAddress(Order $order, $type = 'billing')
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
     * @param Order $order
     * @param string $method
     * @param string $type
     * @return string
     * @throws \Exception
     */
    private function prepareRedirectUrl(Order $order, $method = 'redirect', $type = 'order')
    {
        // check for errors
        if (!in_array($method, ['redirect', 'webhook']))
            throw new \Exception('Cannot generate "' . $method . '" url as method is undefined');
        if (!in_array($type, ['order', 'payment']))
            throw new \Exception('Cannot generate "' . $method . '" url as type is undefined');

        // generate redirect url
        $url = Shopware()->Front()->Router()->assemble([
            'controller'    => 'Mollie',
            'action'        => $method,
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
     * @param Order $order
     * @return string
     */
    private function getLocale(Order $order)
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
     * Checks if current user has a session with the webshop
     *
     * @return bool
     */
    public function hasSession()
    {
        return Shopware()->Session()->offsetGet('userId');
    }

    /**
     * Generate a checksum code.
     *
     * @param \Shopware\Models\Order\Order $order
     * @param null $salt
     * @return string
     */
    public function generateChecksum(Order $order, $salt = null)
    {
        // build checksum
        $handle = [
            $order->getNumber(),
            $order->getInvoiceAmount(),
            $order->getLanguageIso(),
            '\'+f<d$/D5XJe.AB^se\<:*/+M)h,fY6/T-H[q-&T.\'q~gNA(u5{?sd%udn#bBjD{Wy-c}K`L*s</w-@D`42K$c;yu:',
        ];

        // add salt to checksum, if exists
        if ($salt !== null){
            $handle[] = $salt;
        }

        return sha1(implode(',', $handle));
    }

    /**
     * Check the payment status
     *
     * @param \Shopware\Models\Order\Order $order
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function checkPaymentStatus(Order $order)
    {
        // get mollie payment
        $molliePayment = $this->getPaymentObject($order);

        // get the order module
        $sOrder = Shopware()->Modules()->Order();

        if (empty($sOrder))
            return false;

        // set the status
        if ($molliePayment->isPaid()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_COMPLETELY_PAID, true);
            return true;
        }
        elseif ($molliePayment->isAuthorized()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED, true);
            return true;
        }
        elseif ($molliePayment->isCanceled()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED, true);
            return true;
        }
        elseif ($molliePayment->isExpired()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_OPEN, true);
            return true;
        }
        elseif ($molliePayment->isRefunded()) {
            $sOrder->setPaymentStatus($order->getId(), Status::PAYMENT_STATE_RE_CREDITING, true);
            return true;
        }

        return false;
    }
}