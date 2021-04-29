<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;

class TotalAmountTest extends TestCase
{

    /**
     * This test verifies that the property is correctly set in the built transaction item.
     * We already have the gross price of 6.9, so our final total amount is 69.
     *
     * @covers \MollieShopware\Models\TransactionItem::getTotalAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testTotalAmountGrossPrice()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $this->buildItem(6.9, 10, 19);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

        $this->assertEquals(69, $item->getTotalAmount());
    }

    /**
     * This test verifies that the property is correctly set in the built transaction item.
     * We have a net price of 6.9. So we calculate the gross price which is 8.211,
     * that means our total sum has to be 82.11.
     *
     * @covers \MollieShopware\Models\TransactionItem::getTotalAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testTotalAmountWithNetPrice()
    {
        $taxMode = new TaxMode(true, true);

        $item = $this->buildItem(6.9, 10, 19);

        $transaction = new Transaction();
        $transaction->setId(1);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

        $this->assertEquals(82.11, $item->getTotalAmount());
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

}
