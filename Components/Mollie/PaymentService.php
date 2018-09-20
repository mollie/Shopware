<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;

    use MollieShopware\Components\Constants\PaymentStatus;
    use MollieShopware\Models\Transaction;
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

        /**
         * Creates a new transaction and returns the transaction object
         *
         * @param \Shopware_Controllers_Frontend_Mollie $controller
         * @param $order_id
         * @return Transaction
         */
        public function createTransaction($controller, $order_id)
        {

            return $controller->getTransactionRepo()->createNew($order_id);

        }


        /**
         * Start a Mollie transaction and return Mollie payment object
         **/
        public function startTransaction(Order $order)
        {


            $mollie_prepared = $this->prepareOrderForMollie($order);
            $mollie_payment = $this->api->orders->create($mollie_prepared);


            $this->


            return $mollie_payment->getCheckoutUrl();

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

            $vats = $calculate_vats($order->getInvoiceShippingTaxRate(), null, $order->getInvoiceShippingNet());

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

            $phone = '+3164010873';

            return [

                // Mr. or Mrs. (Smith)
                'title'=>'Mr.',
                'givenName'=>'Josse',
                'familyName'=>'Zwols',
                'email'=>'dev@kiener.nl',
                'phone'=>$phone,
                'streetAndNumber'=>'Van Iddekingeweg 125',
                'streetAdditional'=>'Appartment number',
                'postalCode'=>'9821VA',
                'city'=>'Groningen',
                // ISO 3166-1 alpha-2 format (NL, GB, DE, BE, etc)
                'country'=>'NL',

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






        /*public function startTransaction($order_number, $signature, $returnUrl, $webhookUrl, $payment_id, $amount, $currency, $payment_method)
        {

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);


            $paymentOptions = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                ],
                'description' => 'Order #' . $order_number,
                'redirectUrl' => $returnUrl,
                'webhookUrl' => $webhookUrl,
                'method' => str_replace('mollie_', '', $payment_method),
                'metadata' => [
                ],
            ];

            if (strtolower(str_replace('mollie_', '', $payment_method)) === 'ideal'){
                $paymentOptions['issuer'] = $this->getIdealIssuer();
            }

            $remotePayment = $this->api->payments->create($paymentOptions);

            $transaction = $transaction_repository->getByID($payment_id);
            $transaction->setTransactionId($remotePayment->id);
            $transaction_repository->save($transaction);


            return $remotePayment;

        }*/

        public function getPaymentStatus($controller, $signature, $payment_id)
        {

            $paid = false;

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);
            $transaction = $transaction_repository->getByID($payment_id);


            // get Mollie ID
            $remote_transaction_id = $transaction->getTransactionID();

            $status = $this->api->payments->get($remote_transaction_id)->status;

            // get payment status with Mollie
            if ($status == 'paid') {
                $paid = true;

                // store basket
                $controller->doPersistBasket();

                $status = PaymentStatus::PAID;
                $controller->getTransactionRepo()->updateStatus($transaction, $status);

            }

            // return either true or false
            return $paid ? $transaction : false;

        }

        public function restoreSession($signature)
        {

            $newSessionId = Shopware()->Session()->offsetGet('sessionId');

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);
            $transaction = $transaction_repository->findOneBy(['signature' => $signature]);

            $session = json_decode($transaction->getSerializedSession(), 1);
            foreach($session as $k=>$v){

                if ($k === 'sessionId'){
                    continue;
                }
                Shopware()->Session()->offsetSet($k, $v);

            }

            $db = shopware()->container()->get('db');
            $q = $db->prepare('
              UPDATE 
                s_order_basket 
              SET sessionID=? 
              WHERE sessionID=?
            ');

            $q->execute([
                $newSessionId,
                $session['sessionId'],
            ]);


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
