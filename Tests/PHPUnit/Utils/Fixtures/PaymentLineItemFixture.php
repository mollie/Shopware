<?php

namespace MollieShopware\Tests\Utils\Fixtures;

use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;

class PaymentLineItemFixture
{

    /**
     * @return PaymentLineItem
     */
    public function buildItem()
    {
        return new PaymentLineItem(
            'physical',
            'Test Product',
            1,
            'USD',
            24.99,
            49.98,
            19.0,
            5.0,
            'sku-1',
            'https://my-image',
            'https://my-product',
            '{ data: true }',
            '3'
        );
    }
}
