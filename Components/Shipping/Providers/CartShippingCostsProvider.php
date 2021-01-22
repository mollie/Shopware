<?php

namespace MollieShopware\Components\Shipping\Providers;

use MollieShopware\Components\Shipping\ShippingCostsProviderInterface;
use MollieShopware\Components\Shipping\Models\ShippingCosts;

class CartShippingCostsProvider implements ShippingCostsProviderInterface
{

    /**
     * @return ShippingCosts
     */
    public function getShippingCosts()
    {
        $shippingCosts = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts();

        $unitPrice = 0;
        $unitPriceNet = 0;
        $taxRate = 0;

        if (is_array($shippingCosts) && count($shippingCosts)) {
            $taxRate = floatval($shippingCosts['tax']);
            $unitPrice = floatval($shippingCosts['brutto']);
            $unitPriceNet = floatval($shippingCosts['netto']);
        }

        return new ShippingCosts(
            $unitPrice,
            $unitPriceNet,
            $taxRate
        );
    }

}
