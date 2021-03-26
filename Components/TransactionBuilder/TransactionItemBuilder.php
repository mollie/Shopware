<?php

namespace MollieShopware\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionItem;

class TransactionItemBuilder
{

    /**
     * @var TaxMode
     */
    private $taxMode;


    /**
     * @param TaxMode $taxMode
     */
    public function __construct(TaxMode $taxMode)
    {
        $this->taxMode = $taxMode;
    }


    /**
     * @param Transaction $transaction
     * @param BasketItem $basketItem
     * @return TransactionItem
     */
    public function buildTransactionItem(Transaction $transaction, BasketItem $basketItem)
    {
        $unitPrice = $basketItem->getUnitPrice();
        $netPrice = $basketItem->getUnitPriceNet();
        $taxRate = $basketItem->getTaxRate();
        $quantity = $basketItem->getQuantity();

        # we have a net order.
        # this means we need to calculate the correct gross price for Mollie
        if ($this->taxMode->isChargeTaxes() && $this->taxMode->isNetOrder()) {
            $unitPrice = $unitPrice * ($taxRate + 100) / 100;
        }

        # first round our single values
        $netPrice = round($netPrice, 2);
        $unitPrice = round($unitPrice, 2);

        # now calculate the total amount
        # and make sure to round it again
        $totalAmount = $unitPrice * $quantity;
        $totalAmount = round($totalAmount, 2);


        # this line is from the Mollie API
        # it tells us how the vat amount has to be calculated
        # https://docs.mollie.com/reference/v2/orders-api/create-order
        # also round in the end
        $vatAmount = $totalAmount * ($taxRate / ($taxRate + 100));
        $vatAmount = round($vatAmount, 2);


        # if we dont charge taxes
        # just set the values to 0.0
        if (!$this->taxMode->isChargeTaxes()) {
            $taxRate = 0;
            $vatAmount = 0;
        }


        $type = $this->getOrderType($basketItem, $unitPrice);


        $item = new TransactionItem();
        $item->setTransaction($transaction);
        $item->setArticleId($basketItem->getArticleId());
        $item->setBasketItemId($basketItem->getId());
        $item->setName($basketItem->getName());
        $item->setType($type);
        $item->setQuantity($basketItem->getQuantity());
        $item->setUnitPrice($unitPrice);
        $item->setNetPrice($netPrice);
        $item->setTotalAmount($totalAmount);
        $item->setVatRate($taxRate);
        $item->setVatAmount($vatAmount);

        return $item;
    }

    /**
     * @param $basketItem
     * @param $unitPrice
     * @return string
     */
    private function getOrderType($basketItem, $unitPrice)
    {
        if (strpos($basketItem->getOrderNumber(), 'surcharge') !== false) {
            return 'surcharge';
        }

        if (strpos($basketItem->getOrderNumber(), 'discount') !== false) {
            return 'discount';
        }

        if (strpos($basketItem->getOrderNumber(), 'shipping_fee') !== false) {
            return 'shipping_fee';
        }

        if ($basketItem->getEsdArticle() > 0) {
            return 'digital';
        }

        if ($basketItem->getMode() === 2) {
            return 'discount';
        }

        if ($unitPrice < 0) {
            return 'discount';
        }

        return 'physical';
    }
}
