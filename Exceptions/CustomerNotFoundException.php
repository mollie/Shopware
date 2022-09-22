<?php

namespace MollieShopware\Exceptions;

class CustomerNotFoundException extends \Exception
{
    /**
     * CustomerNotFoundException constructor.
     * @param string $message
     *
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
