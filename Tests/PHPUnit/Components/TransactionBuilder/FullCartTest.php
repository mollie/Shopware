<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;


use Doctrine\Common\Collections\ArrayCollection;
use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;


class FullCartTest extends TestCase
{


    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testTaxRate()
    {
        $taxMode = new TaxMode(true, true);

        $basketItem1 = $this->buildItem(5.9, 10, 19);
        $basketItem2 = $this->buildItem(6.9, 10, 19);
        $basketItemShipping = $this->buildShipping(6.71, 1, 19);


        $transaction = new Transaction();
        $transaction->setId(1);


        $builder = new TransactionItemBuilder($taxMode);
        $item1 = $builder->buildTransactionItem($transaction, $basketItem1);
        $item2 = $builder->buildTransactionItem($transaction, $basketItem2);
        $itemShipping = $builder->buildTransactionItem($transaction, $basketItemShipping);

        $items = new ArrayCollection();
        $items->add($item1);
        $items->add($item2);
        $items->add($itemShipping);


        $transaction->setItems($items);

        $totalSum = $item1->getTotalAmount() + $item2->getTotalAmount() + $itemShipping->getTotalAmount();

        #   $this->assertEquals(16, $item1->getTotalAmount());
        #  $this->assertEquals(16, $item2->getTotalAmount());
        $this->assertEquals(160.28, $totalSum);

        # The amount of the order does not match the total amount from the order lines. Expected order amount to be €160.28 but got €160.31.
    }


    /**
     * @param $unitPrice
     * @param $quantity
     * @param $taxRate
     * @return BasketItem
     */
    private function buildItem($unitPrice, $quantity, $taxRate)
    {
        return new BasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'My Article',
            $unitPrice,
            0.0,
            $quantity,
            $taxRate
        );
    }

    /**
     * @param $unitPrice
     * @param $quantity
     * @param $taxRate
     * @return BasketItem
     */
    private function buildShipping($unitPrice, $quantity, $taxRate)
    {
        return new BasketItem(
            1560,
            55,
            'shipping_fee',
            0,
            0,
            'My Article',
            $unitPrice,
            0.0,
            $quantity,
            $taxRate
        );
    }

}
