<?php

namespace MollieShopware\Gateways\Mollie\Exceptions;

class InvalidOrderAmountException extends \Exception
{

    /**
     * @param \Exception $previous
     */
    public function __construct(\Exception $previous)
    {
        parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
    }
}
