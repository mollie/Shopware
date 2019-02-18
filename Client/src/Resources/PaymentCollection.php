<?php

	// Mollie Shopware Plugin Version: 1.4.1

namespace Mollie\Api\Resources;

class PaymentCollection extends \Mollie\Api\Resources\CursorCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return "payments";
    }
    /**
     * @return BaseResource
     */
    protected function createResourceObject()
    {
        return new \Mollie\Api\Resources\Payment($this->client);
    }
}
