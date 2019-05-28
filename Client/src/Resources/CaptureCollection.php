<?php

// Mollie Shopware Plugin Version: 1.4.6

namespace Mollie\Api\Resources;

class CaptureCollection extends \Mollie\Api\Resources\CursorCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return "captures";
    }
    /**
     * @return BaseResource
     */
    protected function createResourceObject()
    {
        return new \Mollie\Api\Resources\Capture($this->client);
    }
}
