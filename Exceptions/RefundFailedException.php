<?php

namespace MollieShopware\Exceptions;

class RefundFailedException extends \Exception
{

    /**
     * @param $orderNumber
     * @param $message
     */
    public function __construct($orderNumber, $message)
    {
        parent::__construct('Mollie Refund failed for order: ' . $orderNumber . '! ' . $message);
    }
}
