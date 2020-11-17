<?php

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
        $logger = Shopware()->Container()->get('pluginlogger');

        // log the error
        switch ($type) {
            case "info":
                $logger->info($message);
                break;
            case "warning":
                $logger->warning($message);
                break;
            case "error":
                $logger->error($message, ['exception' => $exception]);
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