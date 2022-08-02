<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
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
    public function testTotalAmountWithNetPriceHighQuantity()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, false);

        $basketItem = $this->itemFixtures->buildProductItemNet(16.8, 66, 19);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(1319.47, $item->getTotalAmount());
    }

    /**
     * This test verifies our total sum for tax free countries.
     * It's important that we do not charge taxes.
     * Our price is originally based on a gross price, this means that the calculated NET price
     * from Shopware has more than 2 decimals.
     * Also, the cart quantity needs to be a high number, otherwise the rounding isn't tested.
     */
    public function testTotalAmountWithNetPriceHighQuantityAndDecimals()
    {
        $taxMode = new TaxMode(false);
        $builder = new TransactionItemBuilder($taxMode, false);

        # create an item with a calculated NET prices
        # that has multiple decimals and also a high quantity
        $basketItem = $this->itemFixtures->buildProductItemNet(11.9626, 6, 7);


        # mark our item as NET line item
        $basketItem->setIsGrossPrice(false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        # this is the sum of round(11.9626) * 6 quantity = 71.76
        $this->assertEquals(71.76, $item->getTotalAmount());
    }
}
