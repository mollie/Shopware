<?php

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


        public function startTransaction($signature, $returnUrl, $webhookUrl, $payment_id)
        {

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);

            // we don't have a stored order here.
//            $order_repository = Shopware()->Models()->getRepository(Order::class);
//            $order = $order_repository->findOneBy(['signature' => $signature]);
//            $payment_method = $order->getPayment();

            // @todo: find order amount and currency


            $paymentOptions = [
                'amount' => [
                    'value' => number_format(100, 2, '.', ''),
                    'currency' => 'EUR',
                ],
                'description' => 'Order #' . $payment_id,
                'redirectUrl' => $returnUrl,
                'webhookUrl' => $webhookUrl,
                //'method' => str_replace('mollie_', '', $payment_method->getName()),
                'method' => 'ideal',
                'issuer' => $this->getIdealIssuer(),
                'metadata' => [
                ],
            ];

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
                $controller->persistBasket();

                $status = PaymentStatus::PAID;
                $controller->getTransactionRepo()->updateStatus($transaction, $status);



            }

            // return either true or false
            return $paid;

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




    }
