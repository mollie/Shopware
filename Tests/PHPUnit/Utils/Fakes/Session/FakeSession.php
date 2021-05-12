<?php

namespace MollieShopware\Tests\Utils\Fakes\Session;


use MollieShopware\Components\TransactionBuilder\Services\Session\SessionInterface;


class FakeSession implements SessionInterface
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

}
