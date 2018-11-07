<?php

	// Mollie Shopware Plugin Version: 1.3.6

namespace MollieShopware\Components;

class RequestLogger
{
    protected $date;
    protected $action;
    protected $controller;

    public function __construct($action, $controller = null)
    {
        $this->date = date('Y-m-d_His');
        $this->action = $action;
        $this->controller = $controller;
    }

    /**
     * Get path to the log directory (with an added slash)
     */
    protected function getLogDir()
    {
        return __DIR__ . '/../logs/';
    }

    /**
     * Write a message to a log file
     */
    public function write($message, $level = 'info')
    {
        if ($this->shouldLog($level))
        {
            $message = date('Y-m-d_His') . ': ' . $message . "\n\n";

            $orderId = $this->controller ? $this->controller->getOrderNumber() : null;

            $filename = $this->getLogDir() . implode('_', [ $this->date, $this->action, $orderId ]) . '.log';
            file_put_contents($filename, $message, FILE_APPEND);
        }
    }

    public function writeGlobals($level = 'debug')
    {
        if( is_object($this->controller) ) {
            $request = $this->controller->Request();

            $str  = "Params: \n" . print_r($request->getParams(), true) . "\n";
            $str .= "POST: \n" . print_r($request->getPost(), true) . "\n";
            $str .= "SESSION: \n" . print_r($_SESSION, true) . "\n";
            $str .= "COOKIE: \n" . print_r($_COOKIE, true) . "\n";

            return $this->write($str);
        }
    }

    /**
     * Check if the message is of the needed log level
     */
    protected function shouldLog($level)
    {
        $logLevel = strtolower(Shopware()->Config()->getByNamespace('MollieShopware', 'loglevel', 'trace')); // TODO: set default to info

        $level = strtolower($level);

        $levels = array_flip([
            'trace',
            'debug',
            'info',
            'notice',
            'warn',
            'error',
            'fatal'
        ]);

        $index = isset($levels[$level]) ? $levels[$level] : count($levels) - 1;
        $logIndex = isset($levels[$logLevel]) ? $levels[$logLevel] : 0;

        return $index >= $logIndex;
    }
}
