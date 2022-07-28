<?php

namespace MollieShopware\Tests\Components\Support\Services;

use MollieShopware\Components\Support\Services\LogArchiver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LogArchiverTest extends TestCase
{
    /**
     * @var LogArchiver
     */
    private $logArchiver;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logArchiver = new LogArchiver($this->logger);
    }

    /**
     * @test
     * @testdox Method archive() does return a resource when files are provided.
     *
     * @return void
     */
    public function archiveDoesReturnResourceWhenFilesProvided()
    {
        $file = tempnam(sys_get_temp_dir(), 'test-file.log');
        $result = $this->logArchiver->archive('name', [$file]);

        self::assertIsResource($result);
    }

    /**
     * @test
     * @testdox Method archive() does throw an exception when no files are provided.
     *
     * @return void
     */
    public function archiveDoesThrowExceptionWhenNoFilesProvided()
    {
        self::expectExceptionMessage('Could not create zip-archive of log files for the support e-mail, no log files are present');

        $this->logArchiver->archive('name', []);
    }
}
