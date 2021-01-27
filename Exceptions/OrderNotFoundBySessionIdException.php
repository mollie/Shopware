<?php declare(strict_types=1);

namespace MollieShopware\Exceptions;


/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
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
