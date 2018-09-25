<?php

// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Hook_HookArgs;

class AdminShippingSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'sAdmin::sUpdateShipping::after' => 'onUpdateShipping',
        ];
    }

    public function onUpdateShipping(Enlight_Hook_HookArgs $arguments)
    {
        // to do: mollie shipment update
    }
}
