<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Builders;


use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;
use PHPUnit\Framework\TestCase;

class LineItemConverterTest extends TestCase
{


    /**
     * This test verifies that the building of our payment
     * line item works correctly.
     */
    public function testLineItem()
    {
        $builder = new LineItemConverter();

        $lineItem = new PaymentLineItem(
            'physical',
            'T-Shirt Mollie',
            2,
            'EUR',
            14.99,
            29.98,
            19.0,
            5.0,
            'sku-1',
            'img-url',
            'product-url',
            '{ data: true }',
            ''
        );

        $data = $builder->convertItem($lineItem);

        $expected = [
            'type' => 'physical',
            'name' => 'T-Shirt Mollie',
            'quantity' => 2,
            'unitPrice' => [
                'currency' => 'EUR',
                'value' => '14.99',
            ],
            'totalAmount' => [
                'currency' => 'EUR',
                'value' => '29.98',
            ],
            'vatRate' => '19.00',
            'vatAmount' => [
                'currency' => 'EUR',
                'value' => '5.00',
            ],
            'sku' => 'sku-1',
            'imageUrl' => 'img-url',
            'productUrl' => 'product-url',
            'metadata' => '{ data: true }'
        ];

        $this->assertSame($expected, $data);
    }

    /**
     * This test verifies that some values are allowed to be null.
     * But we send them as empty string to the API
     */
    public function testNullValuesAreString()
    {
        $builder = new LineItemConverter();

        $lineItem = new PaymentLineItem(
            'physical',
            'T-Shirt Mollie',
            2,
            'EUR',
            14.99,
            29.98,
            19.0,
            5.0,
            null,
            null,
            null,
            null,
            ''
        );

        $data = $builder->convertItem($lineItem);

        $this->assertEquals('', $data['sku']);
        $this->assertEquals('', $data['imageUrl']);
        $this->assertEquals('', $data['productUrl']);
        $this->assertEquals('', $data['metadata']);
    }

}
