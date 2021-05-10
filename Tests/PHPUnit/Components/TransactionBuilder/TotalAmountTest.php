<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Tests\Utils\Fixtures\BasketLineItemFixture;
use PHPUnit\Framework\TestCase;

class TotalAmountTest extends TestCase
{


    /**
     * @var BasketLineItemFixture
     */
    private $itemFixtures;


    /**
     *
     */
    public function setUp(): void
    {
        $this->itemFixtures = new BasketLineItemFixture();
    }


    /**
     * This test verifies that the property is correctly set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getTotalAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testTotalAmountGrossPrice()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem = $this->itemFixtures->buildProductItemGross(19.99, 66, 19);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(1319.34, $item->getTotalAmount());
    }

    /**
     * This test verifies that the property is correctly set in the built transaction item.
     * We use a net priced article and a very high quantity
     * and verify the rounded final sum.
     *
     * @covers \MollieShopware\Models\TransactionItem::getTotalAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testTotalAmountWithNetPrice()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $basketItem = $this->itemFixtures->buildProductItemNet(16.8, 66, 19);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(1319.34, $item->getTotalAmount());
    }

}
