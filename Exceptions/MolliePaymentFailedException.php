<?php

namespace MollieShopware\Exceptions;

class MolliePaymentFailedException extends \Exception
{

    /**
     * MolliePaymentFailedException constructor.
     * @param $transactionID
     * @param $message
     */
    public function __construct($transactionID, $message)
    {
        parent::__construct('Payment failed for transaction: ' . $transactionID . ', ' . $message);
    }

}
