<?php

namespace MollieShopware\Components\Basket;

interface BasketFactoryInterface
{
    /**
     * Returns the Shopware Basket module.
     *
     * @return \sBasket
     */
    public function create();
}
