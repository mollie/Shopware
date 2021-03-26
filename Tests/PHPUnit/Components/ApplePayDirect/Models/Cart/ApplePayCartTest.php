<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Models\Cart;

use MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart;
use PHPUnit\Framework\TestCase;

class ApplePayCartTest extends TestCase
{

    /**
     * This test verifies that the shipping
     * can be set correctly
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart::setShipping
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart::getShipping
     */
    public function testShipping()
    {
        $cart = new ApplePayCart();
        $cart->setShipping('My Shipping', 3.49);

        $this->assertEquals('My Shipping', $cart->getShipping()->getName());
        $this->assertEquals(1, $cart->getShipping()->getQuantity());
        $this->assertEquals(3.49, $cart->getShipping()->getPrice());
    }

    /**
     * This test verifies that the taxes
     * can be set correctly.
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart::setTaxes
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart::getTaxes
     */
    public function testCurrency()
    {
        $cart = new ApplePayCart();
        $cart->setTaxes(4.49);

        $this->assertEquals('', $cart->getTaxes()->getName());
        $this->assertEquals(1, $cart->getTaxes()->getQuantity());
        $this->assertEquals(4.49, $cart->getTaxes()->getPrice());
    }
}
