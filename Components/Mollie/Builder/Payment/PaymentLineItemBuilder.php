<?php

namespace MollieShopware\Components\Mollie\Builder\Payment;

use MollieShopware\Components\Mollie\Services\LineItemCleaner\MollieLineItemCleaner;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionItem;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;

class PaymentLineItemBuilder
{

    /**
     * @param Transaction $transaction
     * @return array
     */
    public function buildLineItems(Transaction $transaction)
    {
        $orderlines = [];

        /** @var TransactionItem $item */
        foreach ($transaction->getItems() as $item) {

            $orderlines[] = new PaymentLineItem(
                $item->getType(),
                $item->getName(),
                (int)$item->getQuantity(),
                $transaction->getCurrency(),
                $item->getUnitPrice(),
                $item->getTotalAmount(),
                $item->getVatRate(),
                $item->getVatAmount(),
                $item->getSku(),
                null,
                null,
                json_encode(['transaction_item_id' => $item->getId()]),
                $item->getVoucherType()
            );
        }


        $cleaner = new MollieLineItemCleaner();

        # sometimes the advanced promotion suite has duplicate discounts in there.
        # so we just remove these, otherwise we would get the error
        # "amount of line items does not match provided total sum" of mollie
        return $cleaner->removeDuplicateDiscounts($orderlines);
    }

}
