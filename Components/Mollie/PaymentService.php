<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;

    use Mollie\Api\MollieApiClient;
    use MollieShopware\Components\Constants\PaymentStatus;
    use MollieShopware\Models\OrderDetailMollieID;
    use MollieShopware\Models\OrderDetailMollieIDRepository;
    use MollieShopware\Models\Transaction;
    use MollieShopware\Models\TransactionRepository;
    use Shopware\Models\Order\Order;
    use Shopware\Models\Tax\Tax;
    use Symfony\Component\HttpFoundation\Session\Session;

    class PaymentService
    {

        private $apiFactory = null;
        private $api = null;
        private $session = null;

        public function __construct(\MollieShopware\Components\MollieApiFactory $apiFactory, \Enlight_Components_Session_Namespace $session)
        {

            // create API client object
            $this->apiFactory = $apiFactory;
            $this->api = $apiFactory->create();

            $this->session = $session;

        }


        public function createTransaction()
        {

            /**
             * @var TransactionRepository $transaction_repository
             */

            $transaction_repository = Shopware()->container()->get('models')->getRepository(Transaction::class);
            return $transaction_repository->create(null, null);

        }


        /**
         * Start a Mollie transaction and return Mollie payment object
         **/
        public function startTransaction(Order $order, Transaction $transaction)
        {

            /**
             * @var TransactionRepository $transaction_repository
             */

            $mollie_prepared = $this->prepareOrderForMollie($order);
            $mollie_payment = $this->api->orders->create($mollie_prepared);

            $order_detail_repository = Shopware()->container()->get('models')->getRepository(OrderDetailMollieID::class);

            foreach($mollie_payment->lines as $index => $line){

                $item = new OrderDetailMollieID();

                $item->setOrderId($order->getId());
                $item->setMollieRemoteID($line->id);

                $order_detail_repository->save($item);

            }

            $transaction_repository = Shopware()->container()->get('models')->getRepository(Transaction::class);
            $transaction->setOrderID($order->getId());
            $transaction->setMollieID($mollie_payment->id);
            $transaction_repository->save($transaction);

            return $mollie_payment->getCheckoutUrl();

        }

        public function sendOrder(Order $order)
        {
            /**
             * @var OrderDetailMollieIDRepository $order_detail_repository
             */

            $order_detail_repository = Shopware()->container()->get('models')->getRepository(OrderDetailMollieID::class);
            $lines = $order_detail_repository->getShipmentLines($order);



            $shipment = [
                'lines'=>$lines
            ];

            $this->api->shipments->create($shipment);


        }


        /**
         * @param Order $order
         * @return \Mollie\Api\Resources\Order
         */
        public function getPaymentObject(Order $order)
        {

            /**
             * @var TransactionRepository $transaction_repository
             * @var Transaction $transaction
             */

            $transaction_repository = Shopware()->container()->get('models')->getRepository(Transaction::class);
            $transaction = $transaction_repository->getMostRecentTransactionForOrder($order);

            $mollie_payment = $this->api->orders->get($transaction->getMollieID());

            return $mollie_payment;


        }

        /**
         * @param Order $order
         * @return array
         */
        private function prepareOrderForMollie(Order $order)
        {

            $mollie_prepared = [

                'amount'                => null,
                'orderNumber'           => $this->prepareOrderNumberForMollie($order),
                'lines'                 => $this->prepareOrderLinesForMollie($order),
                'billingAddress'        => $this->prepareAddressForMollie($order, 'billing'),
                'shippingAddress'       => $this->prepareAddressForMollie($order, 'shipping'),

                'redirectUrl'           => $this->prepareRedirectUrl($order, 'redirect'),
                'webhookUrl'            => $this->prepareRedirectUrl($order, 'webhook'),

                'locale'                => $this->prepareLocaleForMollie($order),
                'method'                => 'ideal',

                'payment'               => $this->preparePaymentDataForMollie($order),
                'metadata'              => $this->preparePaymentMetaDataForMollie($order),
            ];

            $total_incl = 0.;
            foreach($mollie_prepared['lines'] as $line){
                $total_incl += $line['totalAmount']['value'];
            }

            $mollie_prepared['amount'] = $this->getPriceForMollie($order, $total_incl);

            return $mollie_prepared;

        }

        private function prepareOrderNumberForMollie(Order $order)
        {
            return $order->getNumber();
        }

        /**
         * @param Order $order
         * @return array
         */
        private function prepareOrderLinesForMollie(Order $order)
        {

            $calculate_vats = function($percentage, $total_incl = null, $total_excl = null, $vat = null){

                // turn percentage into factor if needed
                if ($percentage > 1){
                    $percentage = $percentage / 100;
                }

                // check if $total_incl is set and calculate $total_excl and $vat
                if ($total_incl !== null){
                    $total_excl = $total_incl / (1 + $percentage);
                    $vat = $total_excl * $percentage;
                }
                else{
                    // check if $total_excl is set and calculate $total_incl and $vat
                    if ($total_excl !== null){
                        $vat = $total_excl * $percentage;
                        $total_incl = $total_excl + $vat;
                    }
                    else{

                        // check if
                        if ($vat !== null){
                            $total_excl = $vat / $percentage;
                            $total_incl = $total_excl + $vat;
                        }
                        else{
                            throw new \Exception('Either $total_incl, $total_excl or $vat should be set (non null)');
                        }
                    }
                }


                return [
                    'vat'=>$vat,
                    'incl'=>$total_incl,
                    'excl'=>$total_excl
                ];

            };


            $items = [];

            foreach($order->getDetails() as $index => $detail)
            {


                $vats = $calculate_vats($detail->getTaxRate(), $detail->getPrice());

                /**
                 * @var \Shopware\Models\Order\Detail $detail
                 */

                if ($vats['excl'] > 0){

                    $items[] = [

                        'type'=>'physical',
                        'name'=>$detail->getArticleName(),

                        // warning: Mollie does not accept floating point amounts (like 2,5 tons of X)
                        'quantity'=>(int)$detail->getQuantity(),

                        'unitPrice'=>$this->getPriceForMollie($order, $detail->getPrice()),
                        'totalAmount'=>$this->getPriceForMollie($order, $vats['incl'] * $detail->getQuantity()),
                        'vatRate'=>number_format($detail->getTaxRate(), 2, '.', ''),
                        'vatAmount'=>$this->getPriceForMollie($order, $vats['vat'] * $detail->getQuantity() ),
                        'sku'=>$detail->getEan(),
                        'imageUrl'=>null,
                        'productUrl'=>null,

                    ];

                }

            }

            $vats = $calculate_vats($order->getInvoiceShippingTaxRate(), null, $order->getInvoiceShippingNet());

            if ($vats['excl'] > 0){
                $items[] = [

                    'type'          =>      'shipping_fee',
                    'name'          =>      'Shipping fee',
                    'quantity'      =>      1,

                    /**
                     * Shipping price is excl vat, so convert to including vat
                     */
                    'unitPrice'     =>      $this->getPriceForMollie($order, $vats['incl']),
                    'totalAmount'   =>      $this->getPriceForMollie($order, $vats['incl']),
                    'vatRate'       =>      number_format($order->getInvoiceShippingTaxRate(), 2, '.', ''),
                    'vatAmount'     =>      $this->getPriceForMollie($order, $vats['vat']),

                ];
            }

            return $items;


        }

        private function getPriceForMollie(Order $order, $amount)
        {
            return [
                'currency'      =>  $this->getCurrencyForMollie($order),
                'value'        =>  number_format($amount, 2, '.', ''),
            ];
        }

        private function prepareAddressForMollie(Order $order, $type='billing')
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

            $customer = $order->getCustomer();
            $country = $address->getCountry();

            return [

                // Mr. or Mrs. (Smith)
                'title'                 => $address->getSalutation() . '.',
                'givenName'             => $address->getFirstName(),
                'familyName'            => $address->getLastName(),
                'email'                 => $customer->getEmail(),
                //'phone'                 => $customer->getPho
                'streetAndNumber'       => $address->getStreet(),
                'streetAdditional'      => $address->getAdditionalAddressLine1(),
                'postalCode'            => $address->getZipCode(),
                'city'                  => $address->getCity(),
                'country'               => $country ? $country->getIso() : 'NL',

            ];

        }

        private function preparePaymentDataForMollie(Order $order){ return []; }
        private function preparePaymentMetaDataForMollie(Order $order){ return []; }

        private function prepareRedirectUrl(Order $order, $type = 'redirect')
        {

            switch($type){
                case 'redirect':
                    $mode = 'return';

                    break;
                case 'webhook':
                    $mode = 'notify';

                    break;

                default:
                    throw new \Exception('Cannot generate "' . $type . '" url as type is undefined');
            }
            
            $front = Shopware()->Container()->get('Front');

            $rnd = time();



            $url = $front->Router()->assemble([

                'controller'    => 'Mollie',
                'action'        => $mode,
                'forceSecure'   => true,

                'order_number'  => $order->getNumber(),
                'ts'            => $rnd,
                'checksum'      => $this->generateChecksum($order, $rnd)

            ]);


            if (true || defined('LOCAL_MOLLIE_DEV') && LOCAL_MOLLIE_DEV){
                return 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($url);
            }

            return $url;


        }

        private function prepareLocaleForMollie(Order $order)
        {

            $iso = $order->getLanguageIso();

            $translation = [
                'NLD'=>'nl_NL',
                'FRA'=>'fr_FR',
                'ITA'=>'it_IT',
                'ENG'=>'en_US',
                'DLD'=>'de_DE',
                'ESP'=>'es_ES',
                'POR'=>'pt_PT',
                'SVE'=>'sv_SE',
                'FIN'=>'fi_FI',
                'DEN'=>'da_DK',
                'ISL'=>'is_IS',
                'HUN'=>'hu_HU',
                'POL'=>'pl_PL',
                'LAT'=>'lv_LV',
                'LIT'=>'lt_LT',
            ];

            if (isset($translation[$iso])){
                return $translation[$iso];
            }
            else
            {
                return $translation['ENG'];
            }

        }

        /**
         * @param Order $order
         * @return string
         */
        private function getCurrencyForMollie(Order $order)
        {

            /*
             * @Todo: In some exotic cases the currency may need to be translated
             * to another shortcode, but they may be hard to predict at this point
             * */
            return $order->getCurrency();





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

        public function generateChecksum(Order $order, $salt = null)
        {

            $handle = [
                $order->getNumber(),
                $order->getInvoiceAmount(),
                $order->getLanguageIso(),
                '\'+f<d$/D5XJe.AB^se\<:*/+M)h,fY6/T-H[q-&T.\'q~gNA(u5{?sd%udn#bBjD{Wy-c}K`L*s</w-@D`42K$c;yu:',
            ];

            if ($salt !== null){
                $handle[] = $salt;
            }

            return sha1(implode(',', $handle));


        }


        public function checkPaymentStatus(Order $order)
        {

            return true;


        }



    }
