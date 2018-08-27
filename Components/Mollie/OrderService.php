<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;

    class OrderService{

        public function __construct()
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






    }
