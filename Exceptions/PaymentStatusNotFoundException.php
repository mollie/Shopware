<?php

namespace MollieShopware\Exceptions;

class PaymentStatusNotFoundException extends \Exception
{

    /**
     * @param $status
     */
    public function __construct($status)
    {
        parent::__construct('Payment Status: ' . $status . ' not found!');
    }

}
