<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;


use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Tests\Utils\Fixtures\BasketLineItemFixture;
use PHPUnit\Framework\TestCase;


class FullCartTest extends TestCase
{

    /**
     * @var BasketLineItemFixture
     */
    private $itemsFixture;


    /**
     *
     */
    public function setUp(): void
    {
        $this->itemsFixture = new BasketLineItemFixture();
    }

    /**
     * This test verifies the correct amounts for a B2B net based shop.
     * In this type of shop, the article prices are maintained in net prices.
     * This means the prices need to be converted into gross prices for Mollie.
     * The shipping line item however is maintained in gross in Shopware.
     * To avoid wrong calculations, we just reuse that gross price instead of
     * calculating it from the (1 cent off) net price of Shopware.
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testChargeTaxesInNetShop()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);


        # item 1, net price
        $basketItem1 = $this->itemsFixture->buildProductItemNet(5.9, 10, 19);

        # item 2, net price
        $basketItem2 = $this->itemsFixture->buildProductItemNet(6.9, 10, 19);

        # item 3, gross price
        $basketItemShipping = $this->itemsFixture->buildProductItemGross(7.99, 1, 19);


        $item1 = $builder->buildTransactionItem($transaction, $basketItem1);
        $item2 = $builder->buildTransactionItem($transaction, $basketItem2);
        $itemShipping = $builder->buildTransactionItem($transaction, $basketItemShipping);


        $totalGrossSum = $item1->getTotalAmount() + $item2->getTotalAmount() + $itemShipping->getTotalAmount();


        # ---------------------------------------------------------------------------
        $this->assertEquals(70.21, $item1->getTotalAmount());
        $this->assertEquals(82.11, $item2->getTotalAmount());
        $this->assertEquals(7.99, $itemShipping->getTotalAmount());
        # ---------------------------------------------------------------------------
        $this->assertEquals(160.31, $totalGrossSum);
    }

    /**
     *
     */
    public function testChargeTaxesInNetShop2()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem1 = $this->itemsFixture->buildProductItemNet(16.8, 66, 19);
        $basketItemShipping = $this->itemsFixture->buildProductItemGross(7.99, 1, 19);

        $item1 = $builder->buildTransactionItem($transaction, $basketItem1);
        $itemShipping = $builder->buildTransactionItem($transaction, $basketItemShipping);

        $totalGrossSum = $item1->getTotalAmount() + $itemShipping->getTotalAmount();


        # ---------------------------------------------------------------------------
        $this->assertEquals(1319.34, $item1->getTotalAmount());
        $this->assertEquals(7.99, $itemShipping->getTotalAmount());
        # ---------------------------------------------------------------------------
        $this->assertEquals(1327.33, $totalGrossSum);
    }

    /**
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testChargeTaxesInGrossShop()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);


        # item 1, net price
        $basketItem1 = $this->itemsFixture->buildProductItemGross(24.99, 10, 19);

        # item 2, net price
        $basketItem2 = $this->itemsFixture->buildProductItemGross(14.15, 1, 19);

        # item 3, gross price
        $basketItemShipping = $this->itemsFixture->buildProductItemGross(7.99, 1, 19);


        $item1 = $builder->buildTransactionItem($transaction, $basketItem1);
        $item2 = $builder->buildTransactionItem($transaction, $basketItem2);
        $itemShipping = $builder->buildTransactionItem($transaction, $basketItemShipping);


        $totalGrossSum = $item1->getTotalAmount() + $item2->getTotalAmount() + $itemShipping->getTotalAmount();


        # ---------------------------------------------------------------------------
        $this->assertEquals(249.9, $item1->getTotalAmount());
        $this->assertEquals(14.15, $item2->getTotalAmount());
        $this->assertEquals(7.99, $itemShipping->getTotalAmount());
        # ---------------------------------------------------------------------------
        $this->assertEquals(272.04, $totalGrossSum);
    }

    /**
     * This test verifies that the items are correctly calculated without
     * any taxes if this is not turned on for the shop
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testNoTaxCharging()
    {
        $taxMode = new TaxMode(false);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);


        # item 1, net price
        $basketItem1 = $this->itemsFixture->buildProductItemNet(100, 2, 19);

        # item 2, net price
        $basketItem2 = $this->itemsFixture->buildProductItemNet(10, 1, 19);

        # item 3, gross price
        $basketItemShipping = $this->itemsFixture->buildProductItemNet(6.71, 1, 19);


        $item1 = $builder->buildTransactionItem($transaction, $basketItem1);
        $item2 = $builder->buildTransactionItem($transaction, $basketItem2);
        $itemShipping = $builder->buildTransactionItem($transaction, $basketItemShipping);


        $totalGrossSum = $item1->getTotalAmount() + $item2->getTotalAmount() + $itemShipping->getTotalAmount();


        # ---------------------------------------------------------------------------
        $this->assertEquals(200, $item1->getTotalAmount());
        $this->assertEquals(10, $item2->getTotalAmount());
        $this->assertEquals(6.71, $itemShipping->getTotalAmount());
        # ---------------------------------------------------------------------------
        $this->assertEquals(216.71, $totalGrossSum);
    }

}
