<?php

	// Mollie Shopware Plugin Version: 1.3.15

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
