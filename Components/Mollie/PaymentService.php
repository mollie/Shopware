<?php

	// Mollie Shopware Plugin Version: 1.3.14

namespace MollieShopware\Components\Mollie;

    use Mollie\Api\MollieApiClient;
    use Mollie\Api\Resources\Payment;
    use MollieShopware\Components\Constants\PaymentStatus;
    use MollieShopware\Models\OrderLines;
    use MollieShopware\Models\OrderLinesRepository;
    use MollieShopware\Models\Transaction;
    use MollieShopware\Models\TransactionRepository;
    use Shopware\Components\ConfigLoader;
    use Shopware\Models\Order\Order;
    use Shopware\Models\Tax\Tax;
    use Symfony\Component\HttpFoundation\Session\Session;
    use Exception;

    class PaymentService
    {
        // vars
        private $apiFactory = null;
        private $api = null;
        private $session = null;
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
            // create API client object
            $this->apiFactory = $apiFactory;
            $this->api = $apiFactory->create();

            $this->session = $session;
            $this->customEnvironmentVariables = $customEnvironmentVariables;
        }

        /**
         * Create the transaction
         *
         * @return Transaction
         */
        public function createTransaction()
        {
            /** @var TransactionRepository $transactionRepo */
            $transactionRepo = Shopware()->container()->get('models')->getRepository(Transaction::class);

            return $transactionRepo->create(null, null);
        }

        /**
         * Start the transaction
         *
         * @param Order $order
         * @param Transaction $transaction
         * @param array $orderDetails
         * @return null|string
         * @throws \Mollie\Api\Exceptions\ApiException
         */
        public function startTransaction(Order $order, Transaction $transaction, $orderDetails = array())
        {
            // prepare the order for mollie
            $molliePrepared = $this->prepareOrder($order, $orderDetails);

            /** @var Payment $molliePayment */
            $molliePayment = $this->api->orders->create($molliePrepared);

            /** @var OrderLinesRepository $orderLinesRepo */
            $orderLinesRepo = Shopware()->container()->get('models')->getRepository(OrderLines::class);

            // iterate over lines
            foreach($molliePayment->lines as $index => $line) {
                // create new item
                $item = new OrderLines();

                // set vars
                $item->setOrderId($order->getId());
                $item->setMollieOrderlineId($line->id);

                // save item
                $orderLinesRepo->save($item);
            }

            /** @var TransactionRepository $transactionRepo */
            $transactionRepo = Shopware()->container()->get('models')->getRepository(Transaction::class);

            // set transaction vars
            $transaction->setOrderID($order->getId());
            $transaction->setMollieID($molliePayment->id);

            // save transaction
            $transactionRepo->save($transaction);

            return $molliePayment->getCheckoutUrl();
        }

        /**
         * Ship the order
         * @param Order $order
         * @param $mollieId
         * @return bool|\Mollie\Api\Resources\Shipment|null
         * @throws Exception
         */
        public function sendOrder(Order $order, $mollieId)
        {
            // create mollie order object
            $mollieOrder = null;

            try {
                $mollieOrder = $this->api->orders->get($mollieId);
            }
            catch (Exception $ex) {
                throw new Exception('The order could not be found at Mollie.');
            }

            // ship the order
            if (!empty($mollieOrder)) {
                $result = null;

                if (!$mollieOrder->isPaid() && !$mollieOrder->isAuthorized()) {
                    if ($mollieOrder->isCompleted()) {
                        throw new Exception('The order is already completed at Mollie.');
                    }
                    else {
                        throw new Exception('The order doesn\'t seem to be paid or authorized.');
                    }
                }

                try {
                    $result = $mollieOrder->shipAll();
                }
                catch (Exception $ex) {
                    throw new Exception('The order can\'t be shipped.');
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
        public function getPaymentObject(Order $order)
        {
            /** @var TransactionRepository $transactionRepo */
            $transactionRepo = Shopware()->container()->get('models')->getRepository(Transaction::class);

            /** @var Transaction $transaction */
            $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

            /** @var Payment $molliePayment */
            $molliePayment = $this->api->orders->get($transaction->getMollieID());

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
        private function prepareOrder(Order $order, $orderDetails = array())
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
                    'issuer' => $this->getIdealIssuer(),
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
        private function getOrderlines(Order $order, $orderDetails = array())
        {
            // vars
            $items = [];
            $invoiceShippingTaxRate = 0;

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
        private function getInvoiceShippingTaxRate(Order $order)
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
        private function getAddress(Order $order, $type='billing')
        {
            /**
             * @var \Shopware\Models\Order\Billing $address
             * @var \Shopware\Models\Order\Shipping $shipping
             */
            if ($type === 'billing'){
                //\Shopware\Models\Order\Billing
                $address = $order->getBilling();
            }
            else{
                //\Shopware\Models\Order\Shipping
                $address = $order->getShipping();
                $shipping = $order->getShipping();
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
         * @param string $type
         * @return string
         * @throws Exception
         */
        private function prepareRedirectUrl(Order $order, $type = 'redirect')
        {
            // get url type
            switch($type) {
                case 'redirect':
                    $mode = 'return';

                    break;
                case 'webhook':
                    $mode = 'notify';

                    break;

                default:
                    throw new \Exception('Cannot generate "' . $type . '" url as type is undefined');
            }

            // get random number
            $randomNumber = time();

            // generate redirect url
            $url = Shopware()->Front()->Router()->assemble([
                'controller'    => 'Mollie',
                'action'        => $mode,
                'forceSecure'   => true,
                'order_number'  => $order->getNumber(),
                'ts'            => $randomNumber,
                'checksum'      => $this->generateChecksum($order, $randomNumber)
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
         */
        protected function getIdealIssuer()
        {
            $ideal = Shopware()->container()->get('mollie_shopware.payment_methods.ideal');
            return $ideal->getSelectedIssuer();
        }

        /**
         * Checks if current user has a session with the webshop
         * @return bool
         */
        public function hasSession()
        {
            return Shopware()->Session()->offsetGet('userId');
        }

        /**
         * Generate a checksum code
         *
         * @param Order $order
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
         * @param Order $order
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
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::PAID, true);
                return true;
            }
            elseif ($molliePayment->isAuthorized()) {
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::THE_CREDIT_HAS_BEEN_ACCEPTED, true);
                return true;
            }
            elseif ($molliePayment->isCanceled()) {
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::CANCELLED, true);
                return true;
            }
            elseif ($molliePayment->isExpired()) {
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::OPEN, true);
                return true;
            }
            elseif ($molliePayment->isRefunded()) {
                $sOrder->setPaymentStatus($order->getId(), PaymentStatus::RE_CREDITING, true);
                return true;
            }

            return false;
        }
    }
