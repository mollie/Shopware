<?php

// Mollie Shopware Plugin Version: 1.4.8

namespace MollieShopware\Components;

class Logger
{
    /**
     * Log error and throw exception if necessary
     *
     * @param $type
     * @param $message
     * @param null $exception
     * @param bool $throw
     * @throws \Exception
     */
    public static function log($type, $message, $exception = null, $throw = false)
    {
        // log the error
        switch ($type) {
            case "info":
                Shopware()->PluginLogger()->info($message);
                break;
            case "warning":
                Shopware()->PluginLogger()->warning($message);
                break;
            case "error":
                Shopware()->PluginLogger()->error($message, ['exception' => $exception]);
                break;
        }

        // also throw exception
        if ($throw == true) {
            if (is_null($exception))
                $exception = new \Exception($message);

            throw $exception;
        }
    }
}