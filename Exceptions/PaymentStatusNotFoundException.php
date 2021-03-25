<?php

namespace MollieShopware\Exceptions;

class PaymentStatusNotFoundException extends \Exception
{

    /**
     * PaymentStatusNotFoundException constructor.
     * @param $errorMessage
     */
    public function __construct($errorMessage)
    {
        parent::__construct($errorMessage);
    }
}
