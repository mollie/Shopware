<?php

namespace MollieShopware\Tests\Components\MollieApi;

use Doctrine\Common\Collections\ArrayCollection;
use MollieShopware\Components\MollieApi\LineItemsBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionItem;
use PHPUnit\Framework\TestCase;

class LineItemsBuilderTest extends TestCase
{

    /**
     * This test verifies that the line items in the
     * request for Mollie are build correctly.
     * We simply use the transaction and convert it into the JSON array.
     * No additional calculation must be made in that case!
     * That's why we do not have correctly calculated sum and VAT values in here!
     * We always just use the values from the transaction.
     *
     * @covers \MollieShopware\Components\MollieApi\LineItemsBuilder::buildLineItems
     * @covers \MollieShopware\Components\MollieApi\LineItemsBuilder::formatNumber
     * @covers \MollieShopware\Components\MollieApi\LineItemsBuilder::formatNumberWithCurrency
     *
     * @ticket MOL-70
     */
    public function testBuildLineItems()
    {
        # build a line item with wrong values.
        # this is necessary (see description above);
        $item1 = new TransactionItem();
        $item1->setId(1520);
        $item1->setType('test_type');
        $item1->setName('Test Article');
        $item1->setQuantity(2);
        $item1->setUnitPrice(29.9);
        $item1->setTotalAmount(22.45);
        $item1->setVatRate(16);
        $item1->setVatAmount(23.19);

        $items = new ArrayCollection();
        $items->add($item1);

        $transaction = new Transaction();
        $transaction->setCurrency('CHF');
        $transaction->setItems($items);


        # now convert our built transaction
        # into a JSON request parameter structure
        $builder = new LineItemsBuilder();
        $lineItems = $builder->buildLineItems($transaction);

        $expected = [
            [
                'type' => 'test_type',
                'name' => 'Test Article',
                'quantity' => 2,
                'unitPrice' => [
                    'currency' => 'CHF',
                    'value' => '29.90',
                ],
                'totalAmount' => [
                    'currency' => 'CHF',
                    'value' => '22.45',
                ],
                'vatRate' => '16.00',
                'vatAmount' => [
                    'currency' => 'CHF',
                    'value' => '23.19',
                ],
                'sku' => null,
                'imageUrl' => null,
                'productUrl' => null,
                'metadata' => json_encode(['transaction_item_id' => 1520]),
            ]
        ];

        $this->assertEquals($expected, $lineItems);
    }

    /**
     * This test verifies that any duplicate promotions
     * are removed correctly. The Advanced Promotions Suite plugin
     * did somehow add these duplicate entries.
     * We add 2 duplicate promotions (name + price) and one with the
     * same name but a different price.
     * Only the article, 1 of the duplicate promotions and the 3rd promotion
     * should remain in the list of items.
     *
     * @ticket MOL-15
     *
     * @covers \MollieShopware\Components\MollieApi\LineItemsBuilder::buildLineItems
     * @covers \MollieShopware\Components\Helpers\MollieLineItemCleaner::removeDuplicateDiscounts
     */
    public function testRemoveDuplicatePromotions()
    {
        $item1 = new TransactionItem();
        $item1->setType('test_type');
        $item1->setName('Test Article');

        $item2 = new TransactionItem();
        $item2->setType('discount');
        $item2->setName('Super Discount');
        $item2->setUnitPrice(15);

        $item3 = new TransactionItem();
        $item3->setType('discount');
        $item3->setName('Super Discount');
        $item3->setUnitPrice(15);

        $item4 = new TransactionItem();
        $item4->setType('discount');
        $item4->setName('Super Discount');
        $item4->setUnitPrice(30);

        $items = new ArrayCollection();
        $items->add($item1);
        $items->add($item2);
        $items->add($item3);
        $items->add($item4);

        $transaction = new Transaction();
        $transaction->setItems($items);


        # now convert our built transaction
        # into a JSON request parameter structure
        $builder = new LineItemsBuilder();
        $lineItems = $builder->buildLineItems($transaction);

        $this->assertEquals(3, count($lineItems));
        $this->assertEquals('Test Article', $lineItems[0]['name']);
        $this->assertEquals('Super Discount', $lineItems[1]['name']);
        $this->assertEquals('Super Discount', $lineItems[2]['name']);
    }
}
