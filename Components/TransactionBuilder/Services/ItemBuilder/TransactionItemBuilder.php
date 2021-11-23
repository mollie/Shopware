<?php

namespace MollieShopware\Components\TransactionBuilder\Services\ItemBuilder;

use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
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
     * @param MollieBasketItem $basketItem
     * @return TransactionItem
     */
    public function buildTransactionItem(Transaction $transaction, MollieBasketItem $basketItem)
    {
        $unitPrice = $basketItem->getUnitPrice();
        $netPrice = $basketItem->getUnitPriceNet();
        $taxRate = $basketItem->getTaxRate();
        $quantity = $basketItem->getQuantity();


        # if we don't charge taxes just set the values to 0.0
        if (!$this->taxMode->isChargeTaxes()) {
            $taxRate = 0;
        }


        if ($basketItem->isGrossPrice() === false) {

            # if we have a NET price, and we do charge taxes
            # then our unit price is already the correct NET price.
            # in that case we manually calculate the gross price for Mollie
            # based on this unit price
            if ($this->taxMode->isChargeTaxes()) {

                # shopware calculates gross
                $mollieItemPrice = $unitPrice * ($taxRate + 100) / 100;

                if ($this->roundAfterTax) {
                    # also round that sum
                    # Shopware does the same, and also Mollie needs
                    # unit prices with 2 decimals
                    $mollieItemPrice = round($mollieItemPrice, 2);
                }

            } else {

                # if we do not charge taxes
                # then we always have to use the NET price as it is.
                # it could be that the unitPrice is still a gross price...
                # i cannot reproduce this, but it works with this approach.
                $mollieItemPrice = $netPrice;
            }

        } else {

            # if we have a gross price configuration
            # then lets just use the unitPrice which is already the gross price.
            $mollieItemPrice = $unitPrice;
        }


        # now calculate the total amount
        # and make sure to round it again
        $totalAmount = $mollieItemPrice * $quantity;
        $totalAmount = round($totalAmount, 2);


        # this line is from the Mollie API
        # it tells us how the vat amount has to be calculated
        # https://docs.mollie.com/reference/v2/orders-api/create-order
        $vatAmount = $totalAmount * ($taxRate / ($taxRate + 100));

        # also round in the end!
        $vatAmount = round($vatAmount, 2);

        # if we don#t charge taxes
        # then just set the vatAmount to 0,00 again...better safe than sorry
        if (!$this->taxMode->isChargeTaxes()) {
            $vatAmount = 0;
        }


        $item = new TransactionItem();
        $item->setType($this->getOrderType($basketItem, $mollieItemPrice));
        $item->setTransaction($transaction);
        $item->setArticleId($basketItem->getArticleId());
        $item->setBasketItemId($basketItem->getId());
        $item->setName($basketItem->getName());
        $item->setQuantity($basketItem->getQuantity());
        $item->setUnitPrice($mollieItemPrice);
        $item->setNetPrice($netPrice);
        $item->setTotalAmount($totalAmount);
        $item->setVatRate($taxRate);
        $item->setVatAmount($vatAmount);

        if (!empty($basketItem->getOrderNumber())) {
            $item->setSku($basketItem->getOrderNumber());
        }

        # also pass on our voucher type
        # that might be used in further processing
        $item->setVoucherType($basketItem->getVoucherType());


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
