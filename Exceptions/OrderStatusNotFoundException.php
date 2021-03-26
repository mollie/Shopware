<?php

namespace MollieShopware\Exceptions;

class OrderStatusNotFoundException extends \Exception
{

    /**
     * @param $status
     */
    public function __construct($status)
    {
        parent::__construct('Order Status: ' . $status . ' not found!');
    }
}
