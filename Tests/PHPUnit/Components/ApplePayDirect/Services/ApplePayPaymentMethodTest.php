<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Services;

use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\Services\PaymentMethodService;
use PHPUnit\Framework\TestCase;

class ApplePayPaymentMethodTest extends TestCase
{
    private $paymentMethodService;

    public function setUp(): void
    {
        $this->paymentMethodService = new ApplePayPaymentMethod(
            $this->createMock(PaymentMethodService::class)
        );
    }

    public function provideNonApplePaymentMethods()
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

    public function provideApplePaymentMethods()
    {
        return [
            ['mollie_applepay'],
            ['mollie_applepaydirect'],
        ];
    }

    /**
     * Tests if isApplePayPaymentMethod will return false if given method name is not name of Apple Pay method
     *
     * @dataProvider provideNonApplePaymentMethods
     * @covers       \MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod
     */
    public function testIsApplePaymentMethodWillReturnFalseOnOtherMethod($methodName)
    {
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod($methodName));
    }

    /**
     * Tests if isApplePayPaymentMethod will return true if given method name is name of Apple Pay method
     *
     * @dataProvider provideApplePaymentMethods
     * @covers       \MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod
     */
    public function testIsApplePaymentMethodWillReturnOnApplePayMethod($methodName)
    {
        $this->assertTrue($this->paymentMethodService->isApplePayPaymentMethod($methodName));
    }
}
