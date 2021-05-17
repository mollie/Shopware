<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\Services\ItemBuilder\TransactionItemBuilder;
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
     * This test verifies that our total amount is correctly calculated as gross price.
     * We have a gross priced article, so we verify the correct total amount
     * wit a high quantity.
     */
    public function testTotalAmountGrossPrice()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, false);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem = $this->itemFixtures->buildProductItemGross(19.99, 66, 19);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(1319.34, $item->getTotalAmount());
    }

    /**
     * This test verifies that we have the correct total amount
     * if we also have the configuration in Shopware to "round after taxes".
     * In this case, it will convert the net price into a gross price, and immediately
     * round that price before proceeding.
     */
    public function testTotalAmountWithNetPriceRoundAfterTax()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, true);

        $basketItem = $this->itemFixtures->buildProductItemNet(16.8, 66, 19);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(1319.34, $item->getTotalAmount());
    }

    /**
     * This test verifies that our total amount is correctly calculated as gross price.
     * We have a net priced article with a high quantity.
     * In this case, we also test the correct rounding for the total amount.
     */
    public function testTotalAmountWithNetPrice()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, false);

        $basketItem = $this->itemFixtures->buildProductItemNet(16.8, 66, 19);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(1319.47, $item->getTotalAmount());
    }

}
