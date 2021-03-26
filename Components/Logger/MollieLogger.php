<?php

namespace MollieShopware\Components\Logger;

use MollieShopware\Components\Logger\Processors\AnonymousWebProcessor;
use MollieShopware\Components\Logger\Services\IPAnonymizer;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\UidProcessor;

class MollieLogger extends Logger
{

    /**
     * this is the channel name that will be
     * displayed in the backend. It must not contain spaces
     */
    const CHANNEL = 'Mollie';

    /**
     * @var UidProcessor
     */
    private $processorUid;

    /**
     * @var IntrospectionProcessor
     */
    private $processorIntrospection;

    /**
     * @var AnonymousWebProcessor
     */
    private $webProcessor;

    /**
     * @var string
     */
    private $sessionId;


    /**
     * @param $filename
     * @param $retentionDays
     * @param $logLevel
     * @param $sessionId
     */
    public function __construct($filename, $retentionDays, $logLevel, $sessionId)
    {
        $this->sessionId = $sessionId;

        $this->processorUid = new UidProcessor();
        $this->processorIntrospection = new IntrospectionProcessor();
        $this->webProcessor = new AnonymousWebProcessor(new IPAnonymizer('*'));

        $fileHandler = new RotatingFileHandler($filename, $retentionDays, $logLevel);

        parent::__construct(self::CHANNEL, [$fileHandler]);
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function debug($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::DEBUG);

        return parent::debug(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function info($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::INFO);

        return parent::info(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function notice($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::NOTICE);

        return parent::notice(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function warning($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::WARNING);

        return parent::warning(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function error($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::ERROR);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        return parent::error(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function critical($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::CRITICAL);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        return parent::critical(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function alert($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::ALERT);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        return parent::alert(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function emergency($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::EMERGENCY);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        return parent::emergency(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param $logLevel
     * @return array
     */
    private function buildProcessorRecord($logLevel)
    {
        return [
            'level' => $logLevel,
            'extra' => []
        ];
    }

    /**
     * @param $message
     * @return string
     */
    private function modifyMessage($message)
    {
        $sessionPart = substr($this->sessionId, 0, 4) . '...';

        return $message . ' (Session: ' . $sessionPart . ')';
    }


    /**
     * @param array $context
     * @param array $record
     * @return array
     */
    private function extendInfoData(array $context, array $record)
    {
        $additional = [
            'session' => $this->sessionId,
            'processors' => [
                'uid' => $this->processorUid->__invoke($record)['extra'],
                'web' => $this->webProcessor->__invoke($record)['extra'],
            ]
        ];

        return array_merge_recursive($context, $additional);
    }

    /**
     * @param array $context
     * @param array $introspection
     * @param array $record
     * @return array
     */
    private function extendErrorData(array $context, array $introspection, array $record)
    {
        $additional = [
            'session' => $this->sessionId,
            'processors' => [
                'uid' => $this->processorUid->__invoke($record)['extra'],
                'web' => $this->webProcessor->__invoke($record)['extra'],
                'introspection' => $introspection,
            ]
        ];

        return array_merge_recursive($context, $additional);
    }
}
