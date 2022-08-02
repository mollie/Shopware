<?php

namespace MollieShopware\Tests\Components\TransactionBuilder\Models;

use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
use MollieShopware\Models\Voucher\VoucherType;
use PHPUnit\Framework\TestCase;

class MollieBasketItemTest extends TestCase
{

    /**
     * This test verifies that our passed voucher type
     * is correctly used within the item.
     */
    public function testVoucherType()
    {
        $item = new MollieBasketItem('', '', '', '', '', '', '', '', '', '', VoucherType::GIFT);

        $this->assertEquals(VoucherType::GIFT, $item->getVoucherType());
    }

    /**
     * This test verifies that an empty string is returned
     * as voucher type NONE for consistency reasons.
     */
    public function testEmptyVoucherIsNONE()
    {
        $item = new MollieBasketItem('', '', '', '', '', '', '', '', '', '', '');

        $this->assertEquals(VoucherType::NONE, $item->getVoucherType());
    }

    /**
     * This test verifies that a value that is not a real voucher value
     * is returned as NONE.
     */
    public function testInvalidVoucherIsNONE()
    {
        $item = new MollieBasketItem('', '', '', '', '', '', '', '', '', '', 'abc');

        $this->assertEquals(VoucherType::NONE, $item->getVoucherType());
    }
}
