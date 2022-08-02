<?php

namespace MollieShopware\Components\Mollie\Builder\Payment;

use MollieShopware\Models\Transaction;

class OrderNumberBuilder
{

    /**
     * @param Transaction $transaction
     * @param $fallback
     * @return string
     */
    public function buildOrderNumber(Transaction $transaction, $fallback)
    {
        if (!empty($transaction->getOrderNumber())) {
            return (string)$transaction->getOrderNumber();
        }

        return $fallback;
    }
}
