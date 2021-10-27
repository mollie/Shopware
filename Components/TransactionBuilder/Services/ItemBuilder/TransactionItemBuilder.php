<?php

namespace MollieShopware\Components\TransactionBuilder\Services\ItemBuilder;

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
     * @var bool
     */
    private $roundAfterTax;


    /**
     * @param TaxMode $taxMode
     * @param $roundAfterTax
     */
    public function __construct(TaxMode $taxMode, $roundAfterTax)
    {
        $this->taxMode = $taxMode;
        $this->roundAfterTax = $roundAfterTax;
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


        # if we charge taxes but our line item is
        # not already a gross price, then we need to calculate
        # it into a gross price.
        if ($basketItem->isGrossPrice() === false && $this->taxMode->isChargeTaxes()) {

            # shopware calculates gross
            $unitPriceGross = $unitPrice * ($taxRate + 100) / 100;

            if ($this->roundAfterTax) {
                # also round that sum
                # Shopware does the same, and also Mollie needs
                # unit prices with 2 decimals
                $unitPriceGross = round($unitPriceGross, 2);
            }

        } else {
            $unitPriceGross = $unitPrice;
        }

        # now calculate the total amount
        # and make sure to round it again
        $totalAmount = $unitPriceGross * $quantity;
        $totalAmount = round($totalAmount, 2);


        # this line is from the Mollie API
        # it tells us how the vat amount has to be calculated
        # https://docs.mollie.com/reference/v2/orders-api/create-order
        $vatAmount = $totalAmount * ($taxRate / ($taxRate + 100));

        # also round in the end!
        $vatAmount = round($vatAmount, 2);


        # if we dont charge taxes
        # just set the values to 0.0
        if (!$this->taxMode->isChargeTaxes()) {
            $taxRate = 0;
            $vatAmount = 0;
        }


        $item = new TransactionItem();
        $item->setType($this->getOrderType($basketItem, $unitPriceGross));
        $item->setTransaction($transaction);
        $item->setArticleId($basketItem->getArticleId());
        $item->setBasketItemId($basketItem->getId());
        $item->setName($basketItem->getName());
        $item->setQuantity($basketItem->getQuantity());
        $item->setUnitPrice($unitPriceGross);
        $item->setNetPrice($netPrice);
        $item->setTotalAmount($totalAmount);
        $item->setVatRate($taxRate);
        $item->setVatAmount($vatAmount);

        if (!empty($basketItem->getOrderNumber())) {
            $item->setSku($basketItem->getOrderNumber());
        }

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
