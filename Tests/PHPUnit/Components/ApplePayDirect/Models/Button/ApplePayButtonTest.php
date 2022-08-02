<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Models\Button;

use MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton;
use PHPUnit\Framework\TestCase;

class ApplePayButtonTest extends TestCase
{

    /**
     * This test verifies that item mode is OFF
     * for the default button configuration
     */
    public function testItemModeOff()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR', false);

        $this->assertEquals(false, $button->isItemMode());
    }

    /**
     * This test verifies that item mode is ON
     * if a certain product is been assigned.
     */
    public function testItemModeOn()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR', false);
        $button->setItemMode('ABC');

        $this->assertEquals(true, $button->isItemMode());
    }

    /**
     * This test verifies that our array format of the
     * button is correct for the storefront.
     * It also needs to contain the product number
     * if we have item mode ON.
     */
    public function testFormatButton()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR', false);
        $button->setItemMode('ABC');

        $expected = [
            'active' => true,
            'country' => 'NL',
            'currency' => 'EUR',
            'itemMode' => true,
            'addNumber' => 'ABC',
            'displayOptions' => [],
            'requirements' => [
                'phone' => false,
            ],
        ];

        $this->assertEquals($expected, $button->toArray());
    }

    /**
     * This test verifies that we also ask for the
     * phone number if required
     */
    public function testPhoneNumberRequired()
    {
        $button = new ApplePayButton(true, 'NL', 'EUR', true);

        $this->assertEquals(true, $button->toArray()['requirements']['phone']);
    }
}
