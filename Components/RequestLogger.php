<?php

namespace Mollie\Components;

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
			$orderId = $this->controller ? $this->controller->getOrderNumber() : null;

			$filename = $this->getLogDir() . implode('_', [ $this->date, $this->action, $orderId ]) . '.log';
			file_put_contents($filename, $message . "\n\n", FILE_APPEND);
		}
	}

	public function writeGlobals($level = 'debug')
	{
		return $this->write("GET:\n" . print_r($_GET, true) . "\nPOST:\n" . print_r($_POST, true));
	}

	/**
	 * Check if the message is of the needed log level
	 */
	protected function shouldLog($level)
	{
		$logLevel = strtolower(Shopware()->Config()->getByNamespace('Mollie', 'loglevel', 'trace')); // TODO: set default to info

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
