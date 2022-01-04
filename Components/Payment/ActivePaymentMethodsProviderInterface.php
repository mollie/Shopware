<?php

namespace MollieShopware\Components\Payment;

use Shopware\Models\Shop\DetachedShop;

interface ActivePaymentMethodsProviderInterface
{
    /**
     * @param array $parameters
     * @param DetachedShop[] $shops
     *
     * @return mixed
     */
    public function getActivePaymentMethodsFromMollie($parameters = [], $shops = []);
}