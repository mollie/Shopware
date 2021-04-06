<?php

namespace MollieShopware\Exceptions;

class OrderNotFoundException extends \Exception
{

    /**
     * OrderNotFoundException constructor.
     * @param $message
     *
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }

}
