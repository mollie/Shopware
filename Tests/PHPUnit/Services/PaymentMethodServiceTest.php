<?php

namespace MollieShopware\Tests\Services;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Services\PaymentMethodService;
use PHPUnit\Framework\TestCase;


class PaymentMethodServiceTest extends TestCase
{

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
            PaymentMethod::GIROPAY,
            PaymentMethod::IDEAL,
            PaymentMethod::KBC,
            PaymentMethod::KLARNA_PAY_LATER,
            PaymentMethod::KLARNA_SLICE_IT,
            PaymentMethod::PAYPAL,
            PaymentMethod::P24,
            PaymentMethod::DIRECTDEBIT,
            PaymentMethod::SOFORT,
        ];

        $this->assertEquals($expected, PaymentMethodService::getSupportedPaymentMethods());
    }

}
