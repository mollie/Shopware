<?php

namespace _PhpScoperd1ad3ba9842f\GuzzleHttp\Exception;

use _PhpScoperd1ad3ba9842f\Psr\Http\Message\StreamInterface;
/**
 * Exception thrown when a seek fails on a stream.
 */
class SeekException extends \RuntimeException implements \_PhpScoperd1ad3ba9842f\GuzzleHttp\Exception\GuzzleException
{
    private $stream;
    public function __construct(\_PhpScoperd1ad3ba9842f\Psr\Http\Message\StreamInterface $stream, $pos = 0, $msg = '')
    {
        $this->stream = $stream;
        $msg = $msg ?: 'Could not seek the stream to position ' . $pos;
        parent::__construct($msg);
    }
    /**
     * @return StreamInterface
     */
    public function getStream()
    {
        return $this->stream;
    }
}
