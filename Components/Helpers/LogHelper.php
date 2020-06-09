<?php

namespace MollieShopware\Components\Helpers;

use Exception;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
     * @param string                  $message
     * @param string                  $logMethod
     * @param null                    $exception
     * @param ContainerInterface|null $container
     *
     * @return bool
     */
    public static function logMessage($message, $logMethod = self::LOG_ERROR, $exception = null, ContainerInterface $container = null)
    {
        // Set the container
        static::$container = static::getContainer($container);

        // Check if the log method exists
        if (!method_exists(static::pluginLogger(static::$container), $logMethod)) {
            $logMethod = self::LOG_DEBUG;

            // Throw a warning that the log level is unknown
            trigger_error(
                'Using an unknown log level, fallback to debug',
                E_USER_WARNING
            );
        }

        // Check if the plugin logger is available
        if (static::pluginLogger() !== null) {
            return static::pluginLogger()->$logMethod($message, [$exception]);
        }

        return false;
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
        static::$container = static::getContainer($container);

        // Get the plugin logger from the container
        if (static::$pluginLogger === null && static::$container !== null) {
            static::$pluginLogger = static::$container->get('pluginlogger');
        }

        return static::$pluginLogger;
    }

    /**
     * Returns the container interface, or null if not available.
     *
     * @param ContainerInterface|null $container
     *
     * @return ContainerInterface|Container
     */
    private static function getContainer(ContainerInterface $container = null)
    {
        // If the given container is null, but the global container is available, use it
        if (
            $container === null
            && static::$container !== null
        ) {
            $container = static::$container;
        }

        // If the container is still null, try to use the singleton
        if (
            $container === null
        ) {
            try {
                $container = Shopware()->Container();
            } catch (Exception $e) {
                //
            }
        }

        return $container;
    }
}