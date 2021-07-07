<?php

namespace MollieShopware\Tests\Models\Payment;

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
     * This test verifies that if our method INT value is
     * somehow unknown, we alway return at least the PaymentsAPI.
     */
    public function testUnknownIsPaymentsAPI()
    {
        $config = new Configuration();
        $config->setMethodType(45);

        $this->assertSame(PaymentMethodType::PAYMENTS_API, $config->getMethodType());
    }

}
