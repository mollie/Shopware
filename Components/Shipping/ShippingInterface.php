<?php

namespace MollieShopware\Components\Shipping;


use MollieShopware\Components\TransactionBuilder\Models\BasketItem;

interface ShippingInterface
{

    /**
     * @param $countryID
     * @param $paymentID
     * @return mixed
     */
    public function getShippingMethods($countryID, $paymentID);

    /**
     * @param $country
     * @param $shippingMethodId
     * @return array|int|int[]|mixed
     */
    public function getShippingMethodCosts($country, $shippingMethodId);

    /**
     * @param $shippingMethodId
     */
    public function setCartShippingMethodID($shippingMethodId);

    /**
     * @return mixed
     */
    public function getCartShippingMethodID();

    /**
     * @return mixed
     */
    public function getCartShippingMethod();

    /**
     * Gets the shipping costs of
     * the current basket and its items.
     *
     * @return BasketItem
     */
    public function getCartShippingCosts();

}
