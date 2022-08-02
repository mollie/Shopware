<?php

namespace MollieShopware\Tests\Components\Installer;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use PHPUnit\Framework\TestCase;

class PaymentMethodsInstallerTest extends TestCase
{

    /**
     * This test verifies that our important value for the
     * payment action is not touched without recognizing it.
     * It's important to tell Shopware to start a certain action.
     * That action is always the same for Mollie payments and
     * must always exist.
     */
    public function testMollieActionKey()
    {
        $this->assertEquals('frontend/Mollie', PaymentMethodsInstaller::MOLLIE_ACTION_KEY);
    }

    /**
     * This test verifies that we have a valid list of supported payment methods.
     * All these methods are officially supported and tested.
     * The tested function is used to ensure that no other payment methods
     * are accidentally created if they would be available for the merchant.
     */
    public function testSupportedPaymentMethods()
    {
        $expected = [
            PaymentMethod::APPLEPAY_DIRECT,
            PaymentMethod::APPLE_PAY,
            PaymentMethod::BANCONTACT,
            PaymentMethod::BANKTRANSFER,
            PaymentMethod::BELFIUS,
            PaymentMethod::CREDITCARD,
            PaymentMethod::EPS,
            PaymentMethod::GIFTCARD,
            PaymentMethod::GIROPAY,
            PaymentMethod::IDEAL,
            PaymentMethod::KBC,
            PaymentMethod::KLARNA_PAY_LATER,
            PaymentMethod::KLARNA_PAY_NOW,
            PaymentMethod::KLARNA_SLICE_IT,
            PaymentMethod::PAYPAL,
            PaymentMethod::PAYSAFECARD,
            PaymentMethod::P24,
            PaymentMethod::DIRECTDEBIT,
            PaymentMethod::SOFORT,
            PaymentMethod::VOUCHERS,
            PaymentMethod::IN3,
        ];

        $this->assertEquals($expected, PaymentMethodsInstaller::getSupportedPaymentMethods());
    }
}
