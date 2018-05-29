<?php
namespace GuzzleHttpV6\Tests\Psr7;

use GuzzleHttpV6\Psr7;
use GuzzleHttpV6\Psr7\InflateStream;
use GuzzleHttpV6\Psr7\NoSeekStream;

class InflateStreamtest extends \PHPUnit_Framework_TestCase
{
    public function testInflatesStreams()
    {
        $content = gzencode('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        $this->assertEquals('test', (string) $b);
    }

    public function testInflatesStreamsWithFilename()
    {
        $content = $this->getGzipStringWithFilename('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        $this->assertEquals('test', (string) $b);
    }

    public function testInflatesStreamsPreserveSeekable()
    {
        $content = $this->getGzipStringWithFilename('test');
        $seekable = Psr7\stream_for($content);
        $nonSeekable = new NoSeekStream(Psr7\stream_for($content));

        $this->assertTrue((new InflateStream($seekable))->isSeekable());
        $this->assertFalse((new InflateStream($nonSeekable))->isSeekable());
    }

    private function getGzipStringWithFilename($original_string)
    {
        $gzipped = bin2hex(gzencode($original_string));

        $header = substr($gzipped, 0, 20);
        // set FNAME flag
        $header[6]=0;
        $header[7]=8;
        // make a dummy filename
        $filename = '64756d6d7900';
        $rest = substr($gzipped, 20);

        return hex2bin($header . $filename . $rest);
    }
}
