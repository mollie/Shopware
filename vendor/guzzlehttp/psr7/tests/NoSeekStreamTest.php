<?php
namespace GuzzleHttpV6\Tests\Psr7;

use GuzzleHttpV6\Psr7;
use GuzzleHttpV6\Psr7\NoSeekStream;

/**
 * @covers GuzzleHttpV6\Psr7\NoSeekStream
 * @covers GuzzleHttpV6\Psr7\StreamDecoratorTrait
 */
class NoSeekStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot seek a NoSeekStream
     */
    public function testCannotSeek()
    {
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'seek'])
            ->getMockForAbstractClass();
        $s->expects($this->never())->method('seek');
        $s->expects($this->never())->method('isSeekable');
        $wrapped = new NoSeekStream($s);
        $this->assertFalse($wrapped->isSeekable());
        $wrapped->seek(2);
    }

    public function testToStringDoesNotSeek()
    {
        $s = \GuzzleHttpV6\Psr7\stream_for('foo');
        $s->seek(1);
        $wrapped = new NoSeekStream($s);
        $this->assertEquals('oo', (string) $wrapped);

        $wrapped->close();
    }
}
