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

        require_once __DIR__ . '/../../../Shopware/Components/Plugin.php';
    }

    /**
     * Tests if isApplePayPaymentMethod will return false if given method name is not name of Apple Pay method
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod
     */
    public function testIsApplePaymentMethodWillReturnFalseOnOtherMethod()
    {
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_creditcard'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_bancontact'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_banktransfer'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_directdebit'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_eps'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_giftcard'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_giropay'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_ideal'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_inghomepay'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_kbc'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_paypal'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_paysafecard'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_podiumcadeaukaart'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_sofort'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_klarnapaylater'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_klarnasliceit'));
        $this->assertFalse($this->paymentMethodService->isApplePayPaymentMethod('mollie_przelewy24'));
    }

    /**
     * Tests if isApplePayPaymentMethod will return true if given method name is name of Apple Pay method
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod
     */
    public function testIsApplePaymentMethodWillReturnOnApplePayMethod()
    {
        $this->assertTrue($this->paymentMethodService->isApplePayPaymentMethod('mollie_applepay'));
        $this->assertTrue($this->paymentMethodService->isApplePayPaymentMethod('mollie_applepaydirect'));
    }
}
