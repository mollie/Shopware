<?php

namespace MollieShopware\Services\Mollie\Payments\Exceptions;

class ApiNotSupportedException extends \Exception
{

    /**
     * ApiNotSupportedException constructor.
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }

}
