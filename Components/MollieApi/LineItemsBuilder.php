<?php

namespace MollieShopware\Components\MollieApi;

use MollieShopware\Components\Helpers\MollieLineItemCleaner;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionItem;

class LineItemsBuilder
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

            $orderlines[] = [
                'type' => $item->getType(),
                'name' => $item->getName(),
                'quantity' => (int)$item->getQuantity(),
                'unitPrice' => $this->formatNumberWithCurrency($transaction->getCurrency(), $item->getUnitPrice()),
                'totalAmount' => $this->formatNumberWithCurrency($transaction->getCurrency(), $item->getTotalAmount()),
                'vatRate' => $this->formatNumber($item->getVatRate()),
                'vatAmount' => $this->formatNumberWithCurrency($transaction->getCurrency(), $item->getVatAmount()),
                'sku' => null,
                'imageUrl' => null,
                'productUrl' => null,
                'metadata' => json_encode(['transaction_item_id' => $item->getId()]),
            ];
        }


        $cleaner = new MollieLineItemCleaner();

        # sometimes the advanced promotion suite has duplicate discounts in there.
        # so we just remove these, otherwise we would get the error
        # "amount of line items does not match provided total sum" of mollie
        $orderlines = $cleaner->removeDuplicateDiscounts($orderlines);

        return $orderlines;
    }


    /**
     * @param $value
     * @return string
     */
    private function formatNumber($value)
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param $currency
     * @param $amount
     * @return array
     */
    private function formatNumberWithCurrency($currency, $amount)
    {
        return [
            'currency' => $currency,
            'value' => $this->formatNumber($amount)
        ];
    }

}
