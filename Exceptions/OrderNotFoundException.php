<?php

namespace MollieShopware\Exceptions;

class OrderNotFoundException extends \Exception
{

    /**
     * @param $orderNumber
     */
    public function __construct($orderNumber)
    {
        parent::__construct('Order ' . $orderNumber . ' not found!');
    }

}
