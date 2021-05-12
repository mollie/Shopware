<?php

namespace MollieShopware\Tests\Subscriber;

use MollieShopware\Subscriber\IdealIssuersSubscriber;
use PHPUnit\Framework\TestCase;


class iDealSubscriberTest extends TestCase
{

    /**
     * This test verifies that our required subscribers
     * are not changed without recognizing it.
     *
     * @covers IdealIssuersSubscriber::getSubscribedEvents
     */
    public function testSubscribedEvents()
    {
        $expected = [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Account',
            'Shopware_Modules_Admin_UpdatePayment_FilterSql',
        ];

        $this->assertEquals($expected, array_keys(IdealIssuersSubscriber::getSubscribedEvents()));
    }

}
