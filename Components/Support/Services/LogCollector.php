<?php

namespace MollieShopware\Components\Support\Services;

class LogCollector
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $pattern;

    /**
     * Creates a new instance of the log collector.
     *
     * @param string $logPath
     * @param string $pattern
     */
    public function __construct($logPath, $pattern)
    {
        $this->path = $logPath;
        $this->pattern = $pattern;
    }

    /**
     * Returns all log files for the given pattern
     * in the log directory as an array of paths.
     *
     * @return array|false
     */
    public function collect()
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $pattern = sprintf('%s/%s', $this->path, $this->pattern);

        return glob($pattern);
    }
}
