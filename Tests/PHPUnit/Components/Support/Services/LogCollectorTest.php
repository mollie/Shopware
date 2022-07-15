<?php

namespace MollieShopware\Tests\Components\Support\Services;

use MollieShopware\Components\Support\Services\LogCollector;
use PHPUnit\Framework\TestCase;

class LogCollectorTest extends TestCase
{
    private const TEST_VALUE_LOG_FILE_NAME = 'mollie-log.log';

    /**
     * @var LogCollector
     */
    private $logCollector;

    public function setUp(): void
    {
        $this->logCollector = new LogCollector(
            sys_get_temp_dir(),
            sprintf('%s*', self::TEST_VALUE_LOG_FILE_NAME)
        );
    }

    /**
     * @test
     * @testdox Method collect() does return an array with the expected files when the path does exist.
     *
     * @return void
     */
    public function collectDoesReturnArrayWithExpectedFilesWhenPathDoesExist(): void
    {
        $filename = tempnam(sys_get_temp_dir(), self::TEST_VALUE_LOG_FILE_NAME);
        $result = $this->logCollector->collect();

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertTrue(in_array($filename, $result));
    }

    /**
     * @test
     * @testdox Method collect() does return an empty array when the provided path does not exist.
     *
     * @return void
     */
    public function collectDoesReturnEmptyArrayWhenPathDoesNotExist(): void
    {
        $result = (new LogCollector('does-not-exist', '*'))->collect();

        self::assertIsArray($result);
        self::assertEmpty($result);
    }
}
