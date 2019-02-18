<?php

	// Mollie Shopware Plugin Version: 1.4.1

namespace Mollie\Api\Resources;

class MandateCollection extends \Mollie\Api\Resources\CursorCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return "mandates";
    }
    /**
     * @return BaseResource
     */
    protected function createResourceObject()
    {
        return new \Mollie\Api\Resources\Mandate($this->client);
    }
}
