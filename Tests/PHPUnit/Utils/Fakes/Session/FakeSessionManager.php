<?php

namespace MollieShopware\Tests\Utils\Fakes\Session;


use MollieShopware\Components\SessionManager\SessionManagerInterface;
use MollieShopware\Models\Transaction;


class FakeSessionManager implements SessionManagerInterface
{

    /**
     * @var string
     */
    private $sessionId;


    /**
     * @param string $sessionId
     */
    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @param int $days
     */
    public function extendSessionLifespan($days)
    {
    }

    /**
     * @param Transaction $transaction
     * @return string
     */
    public function generateSessionToken(Transaction $transaction)
    {
        return '';
    }

    /**
     * @param Transaction $transaction
     */
    public function deleteSessionToken(Transaction $transaction)
    {
    }

    /**
     * @param Transaction $transaction
     * @param string $requestSessionToken
     */
    public function restoreFromToken(Transaction $transaction, $requestSessionToken)
    {
    }

}
