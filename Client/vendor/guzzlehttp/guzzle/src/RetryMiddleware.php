<?php

namespace _PhpScoperd1ad3ba9842f\GuzzleHttp;

use _PhpScoperd1ad3ba9842f\GuzzleHttp\Promise\PromiseInterface;
use _PhpScoperd1ad3ba9842f\GuzzleHttp\Promise\RejectedPromise;
use _PhpScoperd1ad3ba9842f\GuzzleHttp\Psr7;
use _PhpScoperd1ad3ba9842f\Psr\Http\Message\RequestInterface;
use _PhpScoperd1ad3ba9842f\Psr\Http\Message\ResponseInterface;
/**
 * Middleware that retries requests based on the boolean result of
 * invoking the provided "decider" function.
 */
class RetryMiddleware
{
    /** @var callable  */
    private $nextHandler;
    /** @var callable */
    private $decider;
    /** @var callable */
    private $delay;
    /**
     * @param callable $decider     Function that accepts the number of retries,
     *                              a request, [response], and [exception] and
     *                              returns true if the request is to be
     *                              retried.
     * @param callable $nextHandler Next handler to invoke.
     * @param callable $delay       Function that accepts the number of retries
     *                              and [response] and returns the number of
     *                              milliseconds to delay.
     */
    public function __construct(callable $decider, callable $nextHandler, callable $delay = null)
    {
        $this->decider = $decider;
        $this->nextHandler = $nextHandler;
        $this->delay = $delay ?: __CLASS__ . '::exponentialDelay';
    }
    /**
     * Default exponential backoff delay function.
     *
     * @param int $retries
     *
     * @return int
     */
    public static function exponentialDelay($retries)
    {
        return (int) \pow(2, $retries - 1);
    }
    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     */
    public function __invoke(\_PhpScoperd1ad3ba9842f\Psr\Http\Message\RequestInterface $request, array $options)
    {
        if (!isset($options['retries'])) {
            $options['retries'] = 0;
        }
        $fn = $this->nextHandler;
        return $fn($request, $options)->then($this->onFulfilled($request, $options), $this->onRejected($request, $options));
    }
    private function onFulfilled(\_PhpScoperd1ad3ba9842f\Psr\Http\Message\RequestInterface $req, array $options)
    {
        return function ($value) use($req, $options) {
            if (!\call_user_func($this->decider, $options['retries'], $req, $value, null)) {
                return $value;
            }
            return $this->doRetry($req, $options, $value);
        };
    }
    private function onRejected(\_PhpScoperd1ad3ba9842f\Psr\Http\Message\RequestInterface $req, array $options)
    {
        return function ($reason) use($req, $options) {
            if (!\call_user_func($this->decider, $options['retries'], $req, null, $reason)) {
                return \_PhpScoperd1ad3ba9842f\GuzzleHttp\Promise\rejection_for($reason);
            }
            return $this->doRetry($req, $options);
        };
    }
    private function doRetry(\_PhpScoperd1ad3ba9842f\Psr\Http\Message\RequestInterface $request, array $options, \_PhpScoperd1ad3ba9842f\Psr\Http\Message\ResponseInterface $response = null)
    {
        $options['delay'] = \call_user_func($this->delay, ++$options['retries'], $response);
        return $this($request, $options);
    }
}
