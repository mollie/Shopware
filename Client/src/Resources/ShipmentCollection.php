<?php

// Mollie Shopware Plugin Version: 1.4.2

namespace Mollie\Api\Resources;

class ShipmentCollection extends \Mollie\Api\Resources\BaseCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return 'shipments';
    }
}
