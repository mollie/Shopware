<?php

// Mollie Shopware Plugin Version: 1.4.3

namespace MollieShopware\Components;

class Notifier
{
    /**
     * Shows a JSON exception for the given request. Also sends
     * a 500 server error.
     *
     * @param $error
     * @throws \Exception
     */
    public static function notifyException($error) {
        // log the error
        Logger::log(
            'error',
            $error
        );

        // return the error json
        header('HTTP/1.0 500 Server Error');
        header('Content-Type: text/json');

        echo json_encode([
            'success' => false,
            'message' => $error
        ], JSON_PRETTY_PRINT);

        die();
    }

    /**
     * Shows a JSON thank you message, with a 200 HTTP ok.
     *
     * @param $message
     * @throws \Exception
     */
    public static function notifyOk($message) {
        // log the message
        Logger::log(
            'info',
            $message
        );

        // return the success json
        header('HTTP/1.0 200 Ok');
        header('Content-Type: text/json');

        echo json_encode([
            'success' => true,
            'message' => $message
        ], JSON_PRETTY_PRINT);

        die();
    }
}