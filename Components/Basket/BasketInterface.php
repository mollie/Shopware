<?php

namespace MollieShopware\Components\Basket;


use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
use Shopware\Models\Order\Repository;

interface BasketInterface
{


    /**
     * @param array $userData
     * @return mixed
     */
    public function getMollieBasketLines($userData = []);

}