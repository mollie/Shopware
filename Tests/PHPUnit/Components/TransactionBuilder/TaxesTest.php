<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;


class TaxesTest extends TestCase
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
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $this->buildItem(116, 2, 16);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

        $this->assertEquals(16, $item->getVatRate());
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

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $this->buildItem(116, 2, 16);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

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

        $transaction = new Transaction();
        $transaction->setId(15);

        $item = $this->buildItem(116, 1, 16);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

        $this->assertEquals(0, $item->getVatRate());
        $this->assertEquals(0, $item->getVatAmount());
    }


    /**
     * @return array[]
     */
    public function getVatAmountGrossPricesData()
    {
        return [
            # $expectedVatAmount, $grossPrice, $taxRate, $quantity
            'MOL-70, 1' => [1.89, 1.14, 16, 12],
            'MOL-70, 2' => [1.78, 12.94, 16, 1],
            'MOL-70, 3' => [4.08, 29.61, 16, 1],
            'MOL-70, 4' => [14.57, 3.3, 16, 32],
            'MOL-70, 5' => [1.89, 1.1368, 16, 12],
        ];
    }

    /**
     * @return array[]
     */
    public function getVatAmounNetPricesData()
    {
        return [
            # $expectedVatAmount, $netPrice, $taxRate, $quantity
            'MOL-423, 1' => [11.21, 5.9, 19, 10],
            'MOL-423, 2' => [13.11, 6.9, 19, 10],
        ];
    }

    /**
     * This test verifies that we calculate the correct amount of taxes.
     * This is a crucial test, because it tests the correct rounding of
     * a single line item, which is important when creating orders in Mollie (API).
     * This test covers correct rounding for shops where the unit price
     * of the product is the GROSS price.
     *
     * @dataProvider getVatAmountGrossPricesData
     * @ticket MOL-70
     *
     * @covers       \MollieShopware\Models\TransactionItem::getVatAmount
     * @covers       \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     *
     * @param float $expectedVatAmount
     * @param float $grossPrice
     * @param int $taxRate
     * @param int $quantity
     */
    public function testVatAmountWithGrossPrices($expectedVatAmount, $grossPrice, $taxRate, $quantity)
    {
        $taxMode = new TaxMode(true, false);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $this->buildItem($grossPrice, $quantity, $taxRate);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

        $this->assertEquals($expectedVatAmount, $item->getVatAmount());
    }

    /**
     * This test verifies that we calculate the correct amount of taxes.
     * This is a crucial test, because it tests the correct rounding of
     * a single line item, which is important when creating orders in Mollie (API).
     * This test covers rounding of taxes for shops where the
     * unit price of the product is the NET price.
     *
     * @dataProvider getVatAmounNetPricesData
     * @ticket MOL-423
     *
     * @covers       \MollieShopware\Models\TransactionItem::getVatAmount
     * @covers       \MollieShopware\Components\TransactionBuilder\TransactionItemBuilder::buildTransactionItem
     *
     * @param float $expectedVatAmount
     * @param float $netPrice
     * @param int $taxRate
     * @param int $quantity
     */
    public function testVatAmountWithNetPrices($expectedVatAmount, $netPrice, $taxRate, $quantity)
    {
        $taxMode = new TaxMode(true, true);

        $transaction = new Transaction();
        $transaction->setId(1);

        $item = $this->buildItem($netPrice, $quantity, $taxRate);

        $builder = new TransactionItemBuilder($taxMode);
        $item = $builder->buildTransactionItem($transaction, $item);

        $this->assertEquals($expectedVatAmount, $item->getVatAmount());
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
