<?php

namespace MollieShopware\Components\Basket;

class BasketFactory implements BasketFactoryInterface
{
    /**
     * Returns the Shopware Basket module.
     *
     * @return \sBasket
     */
    public function create()
    {
        return Shopware()->Modules()->Basket();
    }
}
