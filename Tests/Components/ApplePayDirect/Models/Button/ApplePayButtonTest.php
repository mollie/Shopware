<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Models\Button;

use MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton;
use PHPUnit\Framework\TestCase;

class ApplePayButtonTest extends TestCase
{

    /**
     * This test verifies that item mode is OFF
     * for the default button configuration
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton::isItemMode
     */
    public function testItemModeOff()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR');

        $this->assertEquals(false, $button->isItemMode());
    }

    /**
     * This test verifies that item mode is ON
     * if a certain product is been assigned.
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton::setItemMode
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton::isItemMode
     */
    public function testItemModeOn()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR');
        $button->setItemMode('ABC');

        $this->assertEquals(true, $button->isItemMode());
    }

    /**
     * This test verifies that our array format of the
     * button is correct for the storefront.
     * It also needs to contain the product number
     * if we have item mode ON.
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton::isItemMode
     * @covers \MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton::toArray
     */
    public function testFormatButton()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR');
        $button->setItemMode('ABC');

        $expected = array(
            'active' => true,
            'country' => 'NL',
            'currency' => 'EUR',
            'itemMode' => true,
            'addNumber' => 'ABC',
            'displayOptions' => array(),
        );

        $this->assertEquals($expected, $button->toArray());
    }

}
