<?php

namespace MollieShopware\Tests\Utils\Fakes\Basket;

class FakeBasket implements \MollieShopware\Components\Basket\BasketInterface
{

    /**
     * @var array
     */
    private $items;

    /**
     * @param array $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }


    public function getMollieBasketLines($userData = [])
    {
        return $this->items;
    }
}
