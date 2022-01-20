<?php

namespace MollieShopware\Components\Payment;

use Shopware\Models\Shop\Shop;

interface ActivePaymentMethodsProviderInterface
{
    /**
     * @param array $parameters
     * @param array<Shop> $shops
     *
     * @return mixed
     */
    public function getActivePaymentMethods(array $parameters = [], array $shops = []);
}
