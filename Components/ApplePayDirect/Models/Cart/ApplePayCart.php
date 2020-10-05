<?php

namespace MollieShopware\Components\ApplePayDirect\Models\Cart;


class ApplePayCart
{
    
    /**
     * @var ApplePayLineItem[]
     */
    private $items;

    /**
     * @var ApplePayLineItem
     */
    private $shipping;

    /**
     * @var ApplePayLineItem
     */
    private $taxes;

    /**
     */
    public function __construct()
    {
        $this->items = array();

        $this->shipping = null;
        $this->taxes = null;
    }
    
    /**
     * @return ApplePayLineItem
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * @return ApplePayLineItem
     */
    public function getTaxes()
    {
        return $this->taxes;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        $amount = $this->getProductAmount();

        if ($this->shipping instanceof ApplePayLineItem) {
            $amount += $this->shipping->getPrice();
        }

        return $amount;
    }

    /**
     * @return float|int
     */
    public function getProductAmount()
    {
        $amount = 0;

        /** @var ApplePayLineItem $item */
        foreach ($this->items as $item) {
            $amount += ($item->getQuantity() * $item->getPrice());
        }

        return $amount;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param $number
     * @param $name
     * @param $quantity
     * @param $price
     */
    public function addItem($number, $name, $quantity, $price)
    {
        $this->items[] = new ApplePayLineItem($number, $name, $quantity, $price);
    }

    /**
     * @param $name
     * @param $price
     */
    public function setShipping($name, $price)
    {
        $this->shipping = new ApplePayLineItem("SHIPPING", $name, 1, $price);
    }

    /**
     * @param $prices
     */
    public function setTaxes($price)
    {
        $this->taxes = new ApplePayLineItem("TAXES", '', 1, $price);
    }

}
