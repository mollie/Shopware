<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;

class TransactionItemBuilderTest extends TestCase
{

    /**
     * @var BasketItem
     */
    private $sampleItem;


    /**
     *
     */
    public function setUp(): void
    {
        $this->sampleItem = new BasketItem(
            1560,
            55,
            'article-123',
            0,
            0,
            'My Article',
            116.0,
            100.0,
            2,
            16
        );
    }


    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getArticleId
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testArticleId()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(55, $item->getArticleId());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getBasketItemId
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testBasketItemId()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(1560, $item->getBasketItemId());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getName
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testName()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals('My Article', $item->getName());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getQuantity
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testQuantity()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(2, $item->getQuantity());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getUnitPrice
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testUnitPrice()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(116, $item->getUnitPrice());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getNetPrice
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testNetPrice()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(100, $item->getNetPrice());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getTotalAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testTotalAmount()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(116 * 2, $item->getTotalAmount());
    }

    /**
     * This test verifies that the property is correctly
     * set in the built transaction item.
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     */
    public function testVatRate()
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $this->sampleItem);

        $this->assertEquals(16, $item->getVatRate());
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
     * @covers \MollieShopware\Models\TransactionItem::getType
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::getOrderType
     *
     * @param string $expectedType
     * @param string $ordernumber
     * @param int $esdArticle
     * @param int $mode
     * @param float $unitPrice
     */
    public function testType($expectedType, $ordernumber, $esdArticle, $mode, $unitPrice)
    {
        $taxMode = new TaxMode(false, false);

        $basket = new BasketItem(
            1560,
            55,
            $ordernumber,
            $esdArticle,
            $mode,
            'My Article',
            $unitPrice,
            0.0,
            1,
            16
        );

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $basket);

        $this->assertEquals($expectedType, $item->getType());
    }

    /**
     * This test verifies that we correctly charge taxes
     * if we have a tax mode that tells us to do this.
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Models\TransactionItem::getVatAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     *
     * @ticket MOL-70
     */
    public function testTaxesAreCharged()
    {
        $taxMode = new TaxMode(true, false);

        $basket = new BasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'My Article',
            116,
            100.0,
            2,
            16
        );

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $basket);

        $this->assertEquals(16, $item->getVatRate());
        $this->assertEquals(16 * 2, $item->getVatAmount());
    }

    /**
     * This test verifies that our vat amount is 0
     * if we have a tax mode that does not charge any taxes.
     *
     * @covers \MollieShopware\Models\TransactionItem::getVatRate
     * @covers \MollieShopware\Models\TransactionItem::getVatAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     *
     * @ticket MOL-70
     */
    public function testTaxFree()
    {
        $taxMode = new TaxMode(false, false);

        $basket = new BasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'My Article',
            116,
            0.0,
            1,
            16
        );

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $basket);

        $this->assertEquals(0, $item->getVatRate());
        $this->assertEquals(0, $item->getVatAmount());
    }

    /**
     * If we have a NET order, then our unit price is a NET price.
     * The builder will convert this price to a gross price and
     * use that for the transaction item that will be passed on to Mollie.
     *
     * @covers \MollieShopware\Models\TransactionItem::getNetPrice
     * @covers \MollieShopware\Models\TransactionItem::getUnitPrice
     * @covers \MollieShopware\Models\TransactionItem::getTotalAmount
     * @covers \MollieShopware\Models\TransactionItem::getVatAmount
     * @covers \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     *
     * @ticket MOL-70
     */
    public function testGrossPricesAreChargedForNetOrders()
    {
        $taxMode = new TaxMode(true, true);

        $basket = new BasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'My Article',
            100,
            100.0,
            2,
            16
        );

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $basket);

        # we assert that our net price is not touched
        $this->assertEquals(100, $item->getNetPrice());
        # our 100,00 NET is converted to a GROSS
        $this->assertEquals(116, $item->getUnitPrice());
        # and the same is the new gross multiplied with the quantity
        $this->assertEquals(2 * 116, $item->getTotalAmount());
        $this->assertEquals(2 * 16, $item->getVatAmount());
    }

    /**
     * @return array[]
     */
    public function getTaxRoundingData()
    {
        return array(
            'MOL-70, 1' => array(1.89, 1.14, 16, 12),
            'MOL-70, 2' => array(1.78, 12.94, 16, 1),
            'MOL-70, 3' => array(4.08, 29.61, 16, 1),
            'MOL-70, 4' => array(14.57, 3.3, 16, 32),
            'MOL-70, 5' => array(1.89, 1.1368, 16, 12),
        );
    }

    /**
     * This test verifies that we calculate the correct amount of taxes.
     * This is a crucial test, because it tests the correct rounding of
     * a single line item, which is important when creating orders in Mollie (API).
     *
     * @dataProvider getTaxRoundingData
     * @ticket MOL-70
     *
     * @covers       \MollieShopware\Models\TransactionItem::getVatAmount
     * @covers       \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     *
     * @param float $expectedVatAmount
     * @param float $unitPrice
     * @param int $taxRate
     * @param int $quantity
     */
    public function testRoundedTaxAmount($expectedVatAmount, $unitPrice, $taxRate, $quantity)
    {
        $taxMode = new TaxMode(true, false);

        $basket = new BasketItem(
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

        $transaction = new Transaction();
        $transaction->setId(15);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $basket);

        $this->assertEquals($expectedVatAmount, $item->getVatAmount());
    }

}
