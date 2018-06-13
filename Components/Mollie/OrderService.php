<?php

    namespace MollieShopware\Components\Mollie;

    class OrderService{

        public function __construct()
        {

            
        }

        public function persistCurrentBasket($sOrder)
        {

            // persist order
            // temporarily turn off sending emails

            $config = Shopware()->Container()->get('config');
            $config->sendOrderMail = false;


            return $sOrder->saveOrder('12', 'ab' . rand(0,9999), null, false);

        }

        public function checksum($order_id, $hash)
        {

            return sha1($order_id . $hash);

        }






    }
