<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\Services\ItemBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;

class TransactionItemTest extends TestCase
{

    /**
     * @var MollieBasketItem
     */
    private $sampleItem;


    /**
     *
     */
    public function setUp(): void
    {
        $this->sampleItem = new MollieBasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'My Article',
            119,
            100,
            2,
            19,
            ''
        );
    }


    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     */
    public function testArticleId()
    {
        $taxMode = new TaxMode(true);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode, false);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(55, $item->getArticleId());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     */
    public function testBasketItemId()
    {
        $taxMode = new TaxMode(true);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode, false);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(1560, $item->getBasketItemId());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     */
    public function testName()
    {
        $taxMode = new TaxMode(true);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode, false);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals('My Article', $item->getName());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     */
    public function testQuantity()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(2, $item->getQuantity());
    }

    /**
     * This test verifies the correct string type of the item.
     * This one is depending on various properties of the order line.
     *
     * @ticket MOL-70
     *
     * @testWith    ["surcharge", "ord-surcharge", 0, 0, 19.99]
     *              ["digital", "ord-123", 1, 0, 19.99]
     *              ["discount", "ord-discount", 0, 0, 19.99]
     *              ["discount", "ord-123", 0, 2, 19.99]
     *              ["discount", "ord-123", 0, 0, -10.0]
     *              ["physical", "ord-123", 0, 0, 19.99]
     *              ["shipping_fee", "shipping_fee", 0, 0, 19.99]
     *
     * @param string $expectedType
     * @param string $ordernumber
     * @param int $esdArticle
     * @param int $mode
     * @param float $unitPrice
     */
    public function testType($expectedType, $ordernumber, $esdArticle, $mode, $unitPrice)
    {
        $taxMode = new TaxMode(false);
        $builder = new TransactionItemBuilder($taxMode, false);

        $basket = new MollieBasketItem(
            1560,
            55,
            $ordernumber,
            $esdArticle,
            $mode,
            'My Article',
            $unitPrice,
            0.0,
            1,
            16,
            ''
        );

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $builder->buildTransactionItem($transaction, $basket);

        $this->assertEquals($expectedType, $item->getType());
    }


    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     */
    public function testUnitPrice()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(119, $item->getUnitPrice());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     */
    public function testNetPrice()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode, false);

        # mark our item as NET line item
        $this->sampleItem->setIsGrossPrice(false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(100, $item->getNetPrice());
    }
}
