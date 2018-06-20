<?php

    namespace MollieShopware\Components\Mollie;

    class OrderService{

        public function __construct()
        {

            
        }

//        public function persistCurrentBasket($sOrder)
//        {
//
//            // persist order
//            // temporarily turn off sending emails
//
//            $config = Shopware()->Container()->get('config');
//            $config->sendOrderMail = false;
//
//
//            return $sOrder->persistBasket();
//
//        }

        public function getOrderFromDatabase($order_id)
        {

        }

        public function checksum()
        {

            $hash = '';
            foreach(func_get_args() as $argument){
                $hash .= $argument;
            }

            return sha1($hash);

        }

        public function getOrderIdBySignature($signature)
        {





        }




    }
