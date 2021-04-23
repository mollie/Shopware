<?php

namespace MollieShopware\Exceptions;

class TransactionNotFoundException extends \Exception
{

    /**
     * @param string $transactionNumber
     */
    public function __construct($transactionNumber)
    {
        parent::__construct('Transaction ' . $transactionNumber . ' not found!');
    }
}
