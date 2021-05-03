<?php

namespace MollieShopware\Components\Mollie\Builder\Payment;


use MollieShopware\Models\Transaction;

class DescriptionBuilder
{

    /**
     * @param Transaction $transaction
     * @param $uniqueID
     * @return string
     */
    public function buildDescription(Transaction $transaction, $uniqueID)
    {
        if (!empty($transaction->getOrderNumber())) {
            return 'Order ' . $uniqueID;
        }

        return 'Transaction ' . $uniqueID;
    }

}
