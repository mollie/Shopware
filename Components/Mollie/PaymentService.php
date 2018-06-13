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

        public function createPaymentEntry($order_id, $sOrder)
        {

            $transaction = $sOrder->getTransactionRepo()->createNew(
                $order_id
            );


            return $transaction;
        }


        public function startTransaction($order_id, $returnUrl, $webhookUrl, $payment_id)
        {

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);

            $order_repository = Shopware()->Models()->getRepository(Order::class);
            $order = $order_repository->findOneBy(['number' => $order_id]);
            $payment_method = $order->getPayment();


            $paymentOptions = [
                'amount' => [
                    'value' => number_format($order->getInvoiceAmount(), 2, '.', ''),
                    'currency' => $order->getCurrency()
                ],
                'description' => 'Order #'.$order_id,
                'redirectUrl' => $returnUrl,
                'webhookUrl' => $webhookUrl,
                'method' => str_replace('mollie_', '', $payment_method->getName()),
                'issuer' => $this->getIdealIssuer(),
                'metadata' => [

                    'order_id' => $order_id,

                ],
            ];

            $remotePayment = $this->api->payments->create($paymentOptions);

            $transaction = $transaction_repository->getByID($payment_id);
            $transaction->setTransactionId($remotePayment->id);
            $transaction_repository->save($transaction);


            return $remotePayment;

        }

        public function getPaymentStatus($order_id, $payment_id)
        {

            $paid = false;

            $transaction_repository = Shopware()->Container()->get('models')->getRepository(Transaction::class);
            $transaction = $transaction_repository->getByID($payment_id);

            $order_repository = Shopware()->Models()->getRepository(Order::class);
            $order = $order_repository->findOneBy(['number' => $order_id]);

            // get Mollie ID
            $remote_transaction_id = $transaction->getTransactionID();

            $status = $this->api->payments->get($remote_transaction_id)->status;

            // get payment status with Mollie
            if ($status == 'paid') {
                $paid = true;

                if ($order->getPaymentStatus()->getId() !== PaymentStatus::PAID) {
                    Shopware()->Modules()->Order()->setPaymentStatus($order->getID(), PaymentStatus::PAID, true);
                }
            }

            // return either true or false
            return $paid;

        }

        public function reloginUser($order_id)
        {

            $order_repository = Shopware()->Models()->getRepository(Order::class);
            $order = $order_repository->findOneBy(['number' => $order_id]);
            $user = $order->getCustomer();

            $this->session->offsetSet('sUserMail', $user->getEmail());
            $this->session->offsetSet('sUserPassword', $user->getPassword());
            $this->session->offsetSet('sUserId', $user->getID());


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
