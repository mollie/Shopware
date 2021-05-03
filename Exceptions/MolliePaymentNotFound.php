<?php

namespace MollieShopware\Exceptions;

class MolliePaymentNotFound extends \Exception
{

    /**
     * @param int $orderId
     */
    public function __construct($orderId)
    {
        parent::__construct('Mollie payment for order : ' . $orderId . ' not found!');
    }
}
