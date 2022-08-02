<?php

namespace MollieShopware\Tests\Models\Payment;

use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Models\Payment\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{

    /**
     * This test verifies that our default method type is "UNDEFINED".
     * It's necessary to figure out that nothing has been configured yet.
     * And we also want to avoid NULL.
     */
    public function testDefaultMethodType()
    {
        $config = new Configuration();

        $this->assertSame(PaymentMethodType::UNDEFINED, $config->getMethodType());
    }

    /**
     * @return array[]
     */
    public function getPaymentMethodData()
    {
        return [
            'global-config' => [PaymentMethodType::GLOBAL_SETTING, PaymentMethodType::GLOBAL_SETTING],
            'payments-api' => [PaymentMethodType::PAYMENTS_API, PaymentMethodType::PAYMENTS_API],
            'orders-api' => [PaymentMethodType::ORDERS_API, PaymentMethodType::ORDERS_API],
            'unknown-is-global' => [PaymentMethodType::GLOBAL_SETTING, 45],
        ];
    }

    /**
     * This test verifies that we can successfully set
     * a value and get the expected output method.
     *
     * @dataProvider getPaymentMethodData
     * @param mixed $expected
     * @param mixed $value
     */
    public function testMethodType($expected, $value)
    {
        $config = new Configuration();
        $config->setMethodType($value);

        $this->assertSame($expected, $config->getMethodType());
    }

    /**
     * This test verifies that our default order creation is "UNDEFINED".
     * It's necessary to figure out that nothing has been configured yet.
     * And we also want to avoid NULL.
     */
    public function testDefaultOrderCreation()
    {
        $config = new Configuration();

        $this->assertSame(OrderCreationType::UNDEFINED, $config->getOrderCreation());
    }

    /**
     * @return array[]
     */
    public function getOrderCreationData()
    {
        return [
            'global-config' => [OrderCreationType::GLOBAL_SETTING, OrderCreationType::GLOBAL_SETTING],
            'before-payment' => [OrderCreationType::BEFORE_PAYMENT, OrderCreationType::BEFORE_PAYMENT],
            'after-payment' => [OrderCreationType::AFTER_PAYMENT, OrderCreationType::AFTER_PAYMENT],
            'unknown-is-global' => [OrderCreationType::GLOBAL_SETTING, 45],
        ];
    }

    /**
     * This test verifies that we can successfully set
     * a value and get the expected output value.
     *
     * @dataProvider getOrderCreationData
     * @param mixed $expected
     * @param mixed $value
     */
    public function testSetGlobalPaymentMethod($expected, $value)
    {
        $config = new Configuration();
        $config->setOrderCreation($value);

        $this->assertSame($expected, $config->getOrderCreation());
    }
}
