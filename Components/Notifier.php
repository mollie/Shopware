<?php

namespace MollieShopware\Components;

class Notifier
{
    /**
     * Shows a JSON exception for the given request. Also sends
     * a 500 server error.
     *
     * @param $error
     */
    public static function notifyException($error) {
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
     */
    public static function notifyOk($message) {
        header('HTTP/1.0 200 Ok');
        header('Content-Type: text/json');

        echo json_encode([
            'success' => true,
            'message' => $message
        ], JSON_PRETTY_PRINT);

        die();
    }
}