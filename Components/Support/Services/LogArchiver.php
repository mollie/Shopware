<?php

namespace MollieShopware\Components\Support\Services;

use Psr\Log\LoggerInterface;
use RuntimeException;
use ZipArchive;

class LogArchiver
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Creates a new instance of the log archiver.
     *
     * @param LoggerInterface $logger
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns a zip archive with all the
     * collected log files stored inside.
     *
     * @param string $name
     * @param array $files
     *
     * @return false|resource
     */
    public function archive($name, array $files)
    {
        // log an error if the ZipArchive class does not exist
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Could not create zip-archive of log files for the support e-mail, the zip-extension for PHP might not be installed');
        }

        // log an error if no log files are present
        if (empty($files)) {
            throw new RuntimeException('Could not create zip-archive of log files for the support e-mail, no log files are present');
        }

        // creates a temporary file where
        // the zip archive can be stored
        $filename = tempnam(sys_get_temp_dir(), sprintf('%s.zip', $name));

        // log an error if the temporary file could not be created
        if ($filename === false) {
            throw new RuntimeException('Could not create zip-archive of log files for the support e-mail, a temporary file could not be created');
        }

        $archive = new ZipArchive();

        // adds the log files to a zip archive
        if ($archive->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $archive->addFile($file, basename($file));
            }
        } else {
            $this->logger->error(
                sprintf(
                    'Could not create or overwrite zip-archive %s when creating a support e-mail to Mollie.',
                    $filename
                )
            );
        }

        $archive->close();

        return fopen($filename, 'r');
    }
}
