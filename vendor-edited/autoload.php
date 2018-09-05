<?php

	// Mollie Shopware Plugin Version: 1.2.3

/*
     *
     * Shopware uses GuzzleClient v 5.3.2, Mollie uses 6.3.x. To overcome this
     * we changed the namespace for Guzzle in Mollie to Guzzle HttpV6
     *
     * This function loads the V6 version before composer would, making sure
     * that the classes are available when needed for Mollie.
     *
     * @todo: Remove / replace this part as soon as Shopware is upgraded to V 6.3
     *
     * @author: Josse Zwols [Kiener]
     *
     * */


    spl_autoload_register(function($x){

        $filename = '';
        $path = '';

        // string cut open to prevent double replacement when using replace.php
        if (substr($x, 0, 17) === 'Guzzle' . 'HttpV6\\Psr7'){


            // Psr7 namespace
            $class_name = substr($x, 18);
            $filename = $class_name . '.php';
            $path = __DIR__ . '/../vendor/guzzlehttp/psr7/src/';

        }
        else if (substr($x, 0, 20) === 'Guzzle' . 'HttpV6\\Promise'){

            // Promise namespace
            $class_name = substr($x, 21);
            $filename = $class_name . '.php';
            $path = __DIR__ . '/../vendor/guzzlehttp/promises/src/';

        }
        else if (substr($x, 0, 12) === 'Guzzle' . 'HttpV6') {

            // regular namespace
            $class_name = substr($x, 13);
            $filename = $class_name . '.php';
            $path = __DIR__ . '/../vendor/guzzlehttp/guzzle/src/';

        }

        if ($filename && $path){
            $filename = str_replace('\\', '/', $filename);

            if (file_exists($path . $filename)){
                require($path . $filename);
            }
            else{
                die('cannot find ' . $path . $filename);
            }

        }



    });

    require_once(__DIR__ . '/../vendor/guzzlehttp/promises/src/functions.php');
    require_once(__DIR__ . '/../vendor/guzzlehttp/psr7/src/functions.php');