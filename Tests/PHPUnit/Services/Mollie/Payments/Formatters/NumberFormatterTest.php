<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Formatters;

use MollieShopware\Services\Mollie\Payments\ApplePay;
use MollieShopware\Services\Mollie\Payments\Builders\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Formatters\NumberFormatter;
use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;
use MollieShopware\Tests\Utils\Traits\PaymentTestTrait;
use PHPUnit\Framework\TestCase;


class NumberFormatterTest extends TestCase
{

    /**
     * This test verifies that a FLOAT value is
     * always converted to a 2 decimal string.
     */
    public function testFloat()
    {
        $formatter = new NumberFormatter();

        $value = $formatter->formatNumber(24.9);

        $this->assertEquals('24.90', $value);
    }

    /**
     * This test verifies that an INT value is
     * always converted to a 2 decimal string.
     */
    public function testInteger()
    {
        $formatter = new NumberFormatter();

        $value = $formatter->formatNumber(24);

        $this->assertEquals('24.00', $value);
    }

}
