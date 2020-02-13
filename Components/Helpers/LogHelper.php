<?php

namespace MollieShopware\Components\Helpers;

use Psr\Container\ContainerInterface;
use Shopware\Components\Logger;

class LogHelper
{
    const LOG_CRITICAL = 'critical';
    const LOG_DEBUG = 'debug';
    const LOG_ERROR = 'error';
    const LOG_INFO = 'info';
    const LOG_WARNING = 'warning';

    /** @var ContainerInterface */
    private static $container;

    /** @var Logger */
    private static $pluginLogger;

    /**
     * Creates a new instance of the log helper.
     *
     * @param ContainerInterface $container
     */
    public function __construct(
        ContainerInterface $container
    )
    {
        static::$container = $container;
    }

    /**
     * Logs a message to the plugin logger.
     *
     * @param string $message
     * @param string $logMethod
     * @param null $exception
     * @return bool
     */
    public static function logMessage($message, $logMethod = self::LOG_ERROR, $exception = null)
    {
        // Check if the log method exists
        if (!method_exists(static::pluginLogger(), $logMethod)) {
            $logMethod = self::LOG_DEBUG;

            // Throw a warning that the log level is unknown
            trigger_error(
                'Using an unknown log level, fallback to debug',
                E_USER_WARNING
            );
        }

        return static::pluginLogger()->$logMethod($message, [$exception]);
    }

    /**
     * Returns the plugin logger.
     *
     * @param ContainerInterface|null $container
     * @return Logger
     */
    private static function pluginLogger(ContainerInterface $container = null)
    {
        // Set the container
        static::$container = ($container === null ? static::$container : $container);

        // Get the plugin logger from the container
        if (static::$pluginLogger === null && static::$container !== null) {
            static::$pluginLogger = static::$container->get('pluginlogger');
        }

        return static::$pluginLogger;
    }
}