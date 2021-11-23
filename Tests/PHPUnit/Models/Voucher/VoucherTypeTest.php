<?php

namespace MollieShopware\Tests\Models\Payment;

use MollieShopware\Models\Voucher\VoucherType;
use PHPUnit\Framework\TestCase;


class VoucherTypeTest extends TestCase
{

    /**
     *
     */
    public function testValueNONE()
    {
        $this->assertEquals('0', VoucherType::NONE);
    }

    /**
     *
     */
    public function testValueECO()
    {
        $this->assertEquals('1', VoucherType::ECO);
    }

    /**
     *
     */
    public function testValueMEAL()
    {
        $this->assertEquals('2', VoucherType::MEAL);
    }

    /**
     *
     */
    public function testValueGIFT()
    {
        $this->assertEquals('3', VoucherType::GIFT);
    }


    /**
     * This test verifies that our values are correctly
     * converted into the category string that is required
     * by Mollie when starting a payment.
     *
     * @testWith   ["", "0"]
     *             ["eco", "1"]
     *             ["meal", "2"]
     *             ["gift", "3"]
     *             ["", ""]
     *             ["", "4"]
     *
     * @param string $expected
     * @param string $value
     */
    public function testGetMollieCategory($expected, $value)
    {
        $this->assertEquals($expected, VoucherType::getMollieCategory($value));
    }

    /**
     * This test test verifies that only our ECO, MEAL and GIFT
     * vouchers are returned a valid vouchers.
     * The rest is not valid for a purchase.
     *
     * @testWith   [false , "0"]
     *             [true, "1"]
     *             [true, "2"]
     *             [true, "3"]
     *             [false, ""]
     *             [false, "4"]
     *
     * @param bool $expected
     * @param string $value
     */
    public function testIsValidVoucher($expected, $value)
    {
        $this->assertEquals($expected, VoucherType::isValidVoucher($value));
    }

}
