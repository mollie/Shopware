<?php
namespace GuzzleHttpV6\Test\Handler;

use GuzzleHttpV6\Handler\EasyHandle;
use GuzzleHttpV6\Psr7;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttpV6\Handler\EasyHandle
 */
class EasyHandleTest extends TestCase
{
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage The EasyHandle has been released
     */
    public function testEnsuresHandleExists()
    {
        $easy = new EasyHandle;
        unset($easy->handle);
        $easy->handle;
    }
}
