<?php

namespace MollieShopware\Tests\Components\TransactionBuilder\Models;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use PHPUnit\Framework\TestCase;

class BasketItemTest extends TestCase
{

    /**
     * This test verifies that we get the gross price
     * if we access it, and our NET mode is off.
     *
     * @covers \MollieShopware\Components\TransactionBuilder\Models\BasketItem::setNetMode
     * @covers \MollieShopware\Components\TransactionBuilder\Models\BasketItem::getUnitPrice
     */
    public function testUnitPriceNetModeOFF()
    {
        $shipping = new BasketItem(0, 0, '', 0, 0, '', 16.6, 10, 1, 16);
        $shipping->setNetMode(false);

        $this->assertEquals(16.6, $shipping->getUnitPrice());
    }

    /**
     * This test verifies that we get the unit price
     * if we access it, and our NET mode is on.
     *
     * @covers \MollieShopware\Components\TransactionBuilder\Models\BasketItem::setNetMode
     * @covers \MollieShopware\Components\TransactionBuilder\Models\BasketItem::getUnitPrice
     */
    public function testUnitPriceNetModeON()
    {
        $shipping = new BasketItem(0, 0, '', 0, 0, '', 16.6, 10, 1, 16);
        $shipping->setNetMode(true);

        $this->assertEquals(10.0, $shipping->getUnitPrice());
    }
}
