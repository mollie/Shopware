<?php

namespace MollieShopware\Components\Shipping;

use MollieShopware\Components\Shipping\Models\ShippingCosts;

interface ShippingCostsProviderInterface
{

    /**
     * @return ShippingCosts
     */
    public function getShippingCosts();
}
