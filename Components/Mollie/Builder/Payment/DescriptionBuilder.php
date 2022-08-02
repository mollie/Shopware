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
        $orderNumber = (string)$transaction->getOrderNumber();

        if (!empty($orderNumber)) {
            return 'Order ' . $orderNumber;
        }

        return 'Transaction ' . $uniqueID;
    }
}
