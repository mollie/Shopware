<?php

namespace MollieShopware\Exceptions;

class RefundFailedException extends \Exception
{

    /**
     * @param string $orderNumber
     * @param string $message
     */
    public function __construct($orderNumber, $message)
    {
        parent::__construct('Mollie Refund failed for order: ' . $orderNumber . '! ' . $message);
    }
}
