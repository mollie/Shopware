<?php

namespace MollieShopware\Exceptions;

class MollieOrderNotFound extends \Exception
{

    /**
     * @param int $orderId
     */
    public function __construct($orderId)
    {
        parent::__construct('Mollie order : ' . $orderId . ' not found!');
    }
}
