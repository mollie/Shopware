<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;

use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\Services\ItemBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Tests\Utils\Fixtures\BasketLineItemFixture;
use PHPUnit\Framework\TestCase;


class VatAmountTest extends TestCase
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
     * This test verifies that we correctly charge taxes
     * if we have a tax mode that tells us to do this.
     * We enable tax charging, provide a gross priced item and
     * verify that the vat amount is correct.
     *
     * @ticket MOL-70
     */
    public function testVatAmountAdded()
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem = $this->itemFixtures->buildProductItemGross(116, 2, 16);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(16, $item->getVatRate());
        $this->assertEquals(16 * 2, $item->getVatAmount());
    }

    /**
     * This test verifies that our vat amount is 0.00,
     * if we have a tax mode that does not charge any taxes.
     *
     * @ticket MOL-70
     */
    public function testNoVatAmountOnTaxFree()
    {
        $taxMode = new TaxMode(false);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem = $this->itemFixtures->buildProductItemGross(116, 2, 16);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals(0, $item->getVatRate());
        $this->assertEquals(0, $item->getVatAmount());
    }


    /**
     * @return array[]
     */
    public function getVatAmountGrossPricesData()
    {
        return [
            'MOL-70, 1' => [1.89, 1.14, 16, 12],
            'MOL-70, 2' => [1.78, 12.94, 16, 1],
            'MOL-70, 3' => [4.08, 29.61, 16, 1],
            'MOL-70, 4' => [14.57, 3.3, 16, 32],
            'MOL-70, 5' => [1.57, 1.1368, 16, 10],
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
     * @param float $expectedVatAmount
     * @param float $grossPrice
     * @param int $taxRate
     * @param int $quantity
     */
    public function testVatAmountWithGrossPrices($expectedVatAmount, $grossPrice, $taxRate, $quantity)
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem = $this->itemFixtures->buildProductItemGross($grossPrice, $quantity, $taxRate);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals($expectedVatAmount, $item->getVatAmount());
    }


    /**
     * @return array[]
     */
    public function getVatAmountNetPricesData()
    {
        return [
            'MOL-423, 1' => [11.21, 5.9, 19, 10],
            'MOL-423, 2' => [13.11, 6.9, 19, 10],
        ];
    }

    /**
     * This test verifies that we calculate the correct amount of taxes.
     * This is a crucial test, because it tests the correct rounding of
     * a single line item, which is important when creating orders in Mollie (API).
     * This test covers rounding of taxes for shops where the
     * unit price of the product is the NET price.
     *
     * @dataProvider getVatAmountNetPricesData
     * @ticket MOL-423
     *
     * @param float $expectedVatAmount
     * @param float $netPrice
     * @param int $taxRate
     * @param int $quantity
     */
    public function testVatAmountWithNetPrices($expectedVatAmount, $netPrice, $taxRate, $quantity)
    {
        $taxMode = new TaxMode(true);
        $builder = new TransactionItemBuilder($taxMode);

        $transaction = new Transaction();
        $transaction->setId(1);

        $basketItem = $this->itemFixtures->buildProductItemNet($netPrice, $quantity, $taxRate);

        $item = $builder->buildTransactionItem($transaction, $basketItem);

        $this->assertEquals($expectedVatAmount, $item->getVatAmount());
    }

}
