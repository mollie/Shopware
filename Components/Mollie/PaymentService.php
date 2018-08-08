<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;

    use MollieShopware\Components\Constants\PaymentStatus;
    use MollieShopware\Models\Transaction;
    use Shopware\Models\Order\Order;
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

        public function createPaymentEntry($controller, $order_id)
        {

            $transaction = $controller->getTransactionRepo()->createNew(
                $order_id
            );


            return $transaction;
        }


        /**
         * Start a Mollie transaction and return Mollie payment object
         *
         * @param string $signature The signature for this order
         * @param string $returnUrl The return url the user is sent to when the payment has been made or canceled
         * @param string $webhookUrl The url the callback from Mollie is performed on
         * @param string $payment_id The payment ID
         * @param float $amount The amount to charge
         * @param string $currency The currency ISO code to be used
         * @param string $payment_method The payment method name to be used
         * @return object The Mollie payment object (as described in the API docs)
         */
        public function startTransaction($signature, $returnUrl, $webhookUrl, $payment_id, $amount, $currency, $payment_method)
        {

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);


            $paymentOptions = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                ],
                'description' => 'Order #' . $payment_id,
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

        }

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
            return true && Shopware()->Session()->offsetGet('userId');
        }



    }
