<?php

namespace MollieShopware\Components\Payment;

use Shopware\Models\Shop\DetachedShop;

interface ActivePaymentMethodsProviderInterface
{
    /**
     * @param string $value
     * @param string $currency
     * @param DetachedShop[] $shops
     *
     * @return mixed
     */
    public function getActivePaymentMethodsFromMollie($value = '', $currency = '', $shops = []);
}