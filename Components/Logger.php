<?php

	// Mollie Shopware Plugin Version: 1.3.14

namespace MollieShopware\Components;

class Logger
{
    /**
     * Log message
     *
     * @param string $type
     * @param string $message
     * @param \Exception $exception
     */
    public static function log($type, $message, $exception = null)
    {
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
    }
}