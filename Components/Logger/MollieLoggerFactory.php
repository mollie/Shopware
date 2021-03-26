<?php

namespace MollieShopware\Components\Logger;

use MollieShopware\Components\Config;

class MollieLoggerFactory
{
    /**
     * value for the session id if no
     * session exists
     */
    const SESSION_NOTSET = 'not-set';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $retentionDays;


    /**
     * @param Config $config
     * @param $filename
     * @param $retentionDays
     */
    public function __construct(Config $config, $filename, $retentionDays)
    {
        $this->config = $config;
        $this->filename = $filename;
        $this->retentionDays = $retentionDays;
    }


    /**
     * @return MollieLogger
     */
    public function createLogger()
    {
        $sessionId = $this->getSessionId();

        return new MollieLogger(
            $this->filename,
            $this->retentionDays,
            $this->config->getLogLevel(),
            $sessionId
        );
    }

    /**
     * Gets either the sessionID or a
     * placeholder depending on if the
     * session exists or not.
     *
     * @return string
     */
    private function getSessionId()
    {
        if (!isset($_SESSION['Shopware'])) {
            return self::SESSION_NOTSET;
        }

        if (!isset($_SESSION['Shopware']['sessionId'])) {
            return self::SESSION_NOTSET;
        }

        $sessionId = $_SESSION['Shopware']['sessionId'];

        if (empty($sessionId)) {
            return self::SESSION_NOTSET;
        }

        return $sessionId;
    }
}
