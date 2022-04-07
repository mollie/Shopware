<?php

namespace MollieShopware\Components\Support\Services;

use Psr\Log\LoggerInterface;
use ZipArchive;

class LogArchiver
{
    /**
     * @var string
     */
    private $storagePath;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Creates a new instance of the log archiver.
     *
     * @param string $storagePath
     * @param LoggerInterface $logger
     */
    public function __construct($storagePath, $logger)
    {
        $this->storagePath = $storagePath;
        $this->logger = $logger;

        $this->createDirectoryIfNotExists();
    }

    /**
     * Returns a zip archive with all the
     * collected log files stored inside.
     *
     * @param string $name
     * @param array $logs
     *
     * @return false|string
     */
    public function archive($name, array $logs)
    {
        if (empty($logs) || !is_dir($this->storagePath)) {
            return false;
        }

        $archive = new ZipArchive();
        $filename = sprintf('%s/%s.zip', $this->storagePath, $name);

        if ($archive->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($logs as $file) {
                $archive->addFile($file, basename($file));
            }
        } else {
            $this->logger->error(sprintf('Could not create or overwrite attachment %s', $filename));
        }

        return file_get_contents($filename);
    }

    /**
     * Creates the directory where the zip file
     * is being stored, if it doesn't exist.
     *
     * @return void
     */
    private function createDirectoryIfNotExists()
    {
        if (is_dir($this->storagePath)) {
            return;
        }

        $created = mkdir($this->storagePath, 0775, true);

        if (!$created) {
            $this->logger->error(sprintf('Could not create directory %s', $this->storagePath));
        }
    }
}
