<?php

namespace MollieShopware\Exceptions;

class OrderNotFoundBySessionIdException extends \Exception
{
    /**
     * @param $sessionId
     */
    public function __construct($sessionId)
    {
        parent::__construct('Order not found by sessionId: ' . $sessionId);
    }
}
