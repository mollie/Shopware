<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Services;

use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\Services\PaymentMethodService;
use PHPUnit\Framework\TestCase;

class ApplePayPaymentMethodTest extends TestCase
{

    /**
     * @var ApplePayPaymentMethod
     */
    private $applepay;


    /**
     *
     */
    public function setUp(): void
    {
        $this->applepay = new ApplePayPaymentMethod(
            $this->createMock(PaymentMethodService::class)
        );
    }

    /**
     * Tests if isApplePayPaymentMethod will return false if given
     * method name is not name of Apple Pay method
     *
     * @dataProvider getNonApplePaymentMethods
     */
    public function testNonApplePayMethods($methodName)
    {
        $this->assertFalse($this->applepay->isApplePayPaymentMethod($methodName));
    }

    /**
     * Tests if isApplePayPaymentMethod will return true if given
     * method name is name of Apple Pay method
     *
     * @dataProvider getApplePaymentMethods
     */
    public function testApplePayMethods($methodName)
    {
        $this->assertTrue($this->applepay->isApplePayPaymentMethod($methodName));
    }

    /**
     * This test verifies that our function can be used with NULL.
     * Somehow NULL is happening for some customers out there.
     */
    public function testIsNotApplePayWithNull()
    {
        $this->assertFalse($this->applepay->isApplePayPaymentMethod(null));
    }

    /**
     * This test verifies that our function can be used with an empty string.
     */
    public function testIsNotApplePayWithEmptyString()
    {
        $this->assertFalse($this->applepay->isApplePayPaymentMethod(''));
    }

    /**
     * @return \string[][]
     */
    public function getNonApplePaymentMethods()
    {
        return [
            ['mollie_creditcard'],
            ['mollie_bancontact'],
            ['mollie_banktransfer'],
            ['mollie_directdebit'],
            ['mollie_eps'],
            ['mollie_giftcard'],
            ['mollie_giropay'],
            ['mollie_ideal'],
            ['mollie_inghomepay'],
            ['mollie_kbc'],
            ['mollie_paypal'],
            ['mollie_paysafecard'],
            ['mollie_podiumcadeaukaart'],
            ['mollie_sofort'],
            ['mollie_klarnapaylater'],
            ['mollie_klarnasliceit'],
            ['mollie_przelewy24'],
        ];
    }

    /**
     * @return \string[][]
     */
    public function getApplePaymentMethods()
    {
        return [
            ['mollie_applepay'],
            ['mollie_applepaydirect'],
        ];
    }

}
