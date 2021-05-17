<?php

namespace MollieShopware\Tests\Components\Installer;

use MollieShopware\Components\Services\PaymentMethodService;
use PHPUnit\Framework\TestCase;

class PaymentMethodServiceTest extends TestCase
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
        $this->assertEquals('frontend/Mollie', PaymentMethodService::MOLLIE_ACTION_KEY);
    }
}
