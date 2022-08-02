<?php

namespace MollieShopware\Tests\Utils\Fakes\Shipping;

use MollieShopware\Components\Shipping\ShippingInterface;
use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;

class FakeShipping implements ShippingInterface
{


    /**
     * @var MollieBasketItem
     */
    private $item;

    /**
     * @param MollieBasketItem $item
     */
    public function __construct(MollieBasketItem $item)
    {
        $this->item = $item;
    }


    public function getShippingMethods($countryID, $paymentID)
    {
        // TODO: Implement getShippingMethods() method.
    }

    public function getShippingMethodCosts($country, $shippingMethodId)
    {
        // TODO: Implement getShippingMethodCosts() method.
    }

    public function setCartShippingMethodID($shippingMethodId)
    {
        // TODO: Implement setCartShippingMethodID() method.
    }

    public function getCartShippingMethodID()
    {
        // TODO: Implement getCartShippingMethodID() method.
    }

    public function getCartShippingMethod()
    {
        // TODO: Implement getCartShippingMethod() method.
    }

    public function getCartShippingCosts()
    {
        return $this->item;
    }
}
