<?php

namespace MollieShopware\Components\Transaction;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Models\Transaction;


class TransactionStatusValidator
{

    /**
     * @param Transaction $transaction
     * @param string $molliePaymentStatus
     * @return bool
     */
    public function isTransactionPending(Transaction $transaction, $molliePaymentStatus)
    {
        # if it's expired, failed or somehow cancelled we can remove it here.
        # ATTENTION, a webhook must only check for EXPIRED, because the storefront
        # will handle failed and cancellation due to the race conditions of a too early received webhook.
        # but this code here is for general cleanups, so its OK to remove snapshots
        # that have been failed.
        if (PaymentStatus::isFailedStatus($molliePaymentStatus)) {
            return false;
        }

        # if we don't have an order, we cannot delete it
        # there might be an even in progress to create the order
        # but if we already have an order, then it's fine to delete it
        if (empty($transaction->getOrderNumber())) {
            return true;
        }

        if (empty($transaction->getOrderId())) {
            return true;
        }

        return false;
    }

}
