<?php

namespace MollieShopware\Tests\Components\Constants;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\MollieShopware;
use PHPUnit\Framework\TestCase;

class PaymentMethodTypeTest extends TestCase
{

    /**
     * This test verifies that the correct INT value is used.
     * This one will be saved in the database for the payment specific configuration.
     */
    public function testUndefinedValue()
    {
        $this->assertEquals(0, PaymentMethodType::UNDEFINED);
    }

    /**
     * This test verifies that the correct INT value is used.
     * This one will be saved in the database for the payment specific configuration.
     */
    public function testGlobalSettingsValue()
    {
        $this->assertEquals(1, PaymentMethodType::GLOBAL_SETTING);
    }

    /**
     * This test verifies that the correct INT value is used.
     * This one will be saved in the database for the payment specific configuration.
     */
    public function testPaymentsApiValue()
    {
        $this->assertEquals(2, PaymentMethodType::PAYMENTS_API);
    }

    /**
     * This test verifies that the correct INT value is used.
     * This one will be saved in the database for the payment specific configuration.
     */
    public function testOrdersApiValue()
    {
        $this->assertEquals(3, PaymentMethodType::ORDERS_API);
    }


    /**
     * @return array[]
     */
    public function getPaymentMethods()
    {
        return [
            # [ PaymentsAPI-allowed, Payment Method ]
            # ----------------------------------------------
            [false, PaymentMethod::BILLIE],
            [false, PaymentMethod::KLARNA_PAY_LATER],
            [false, PaymentMethod::KLARNA_PAY_NOW],
            [false, PaymentMethod::KLARNA_SLICE_IT],
            [false, PaymentMethod::VOUCHERS],
            # ----------------------------------------------
            [true, PaymentMethod::PAYPAL],
            [true, PaymentMethod::APPLEPAY_DIRECT],
            [true, PaymentMethod::APPLE_PAY],
            [true, PaymentMethod::SOFORT],
            [true, PaymentMethod::P24],
            [true, PaymentMethod::IDEAL],
            [true, PaymentMethod::GIROPAY],
            [true, PaymentMethod::CREDITCARD],
            [true, PaymentMethod::BELFIUS],
            [true, PaymentMethod::BANKTRANSFER],
            [true, PaymentMethod::BANCONTACT],
            [true, PaymentMethod::EPS],
            [true, PaymentMethod::KBC],
            [true, PaymentMethod::TWINT],
            [true, PaymentMethod::BLIK],
        ];
    }

    /**
     *
     * This test verifies that we always know if the payments api is working
     * for a payment method, or if the orders API is required.
     * Some payment methods do not work with the payments api!
     *
     * @dataProvider getPaymentMethods
     *
     * @param bool $allowed
     * @param string $paymentMethod
     */
    public function testIsPaymentsApiAllowed($allowed, $paymentMethod)
    {
        # test without prefix
        $this->assertEquals($allowed, PaymentMethodType::isPaymentsApiAllowed($paymentMethod));

        # test with prefix
        $this->assertEquals($allowed, PaymentMethodType::isPaymentsApiAllowed(MollieShopware::PAYMENT_PREFIX . $paymentMethod));
    }
}
