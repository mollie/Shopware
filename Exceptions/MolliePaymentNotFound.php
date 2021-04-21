<?php

namespace MollieShopware\Exceptions;

class MolliePaymentNotFound extends \Exception
{

    /**
     * @param $orderId
     */
    public function __construct($orderId)
    {
        parent::__construct('Mollie payment for order : ' . $orderId . ' not found!');
    }
}
