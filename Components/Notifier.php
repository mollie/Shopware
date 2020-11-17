<?php

namespace MollieShopware\Components;

class Notifier
{
    /**
     * Shows a JSON exception for the given request.
     *
     * @param $error
     * @param null $exception
     * @throws \Exception
     */
    public static function notifyException($error, $exception = null) {
        // log the error
        Logger::log(
            'error',
            $error,
            $exception
        );

        self::notify(false, $error, '500 Server Error');
    }

    /**
     * Shows a JSON success message.
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

        self::notify(true, $message);
    }

    /**
     * Notify a message as json.
     *
     * @param bool $success
     * @param string $message
     * @param string $header
     */
    private static function notify($success, $message = '', $header = '200 Ok')
    {
        // return the json
        header('HTTP/1.0 ' . $header);
        header('Content-Type: text/json');

        echo json_encode([
            'success' => $success,
            'message' => $message
        ], JSON_PRETTY_PRINT);

        die();
    }
}