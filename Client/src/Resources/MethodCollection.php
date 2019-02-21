<?php

// Mollie Shopware Plugin Version: 1.4.3

namespace Mollie\Api\Resources;

class MethodCollection extends \Mollie\Api\Resources\BaseCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return "methods";
    }
}
