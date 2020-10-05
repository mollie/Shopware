<?php

namespace MollieShopware\Components\ApplePayDirect\Models\Cart;

class ApplePayLineItem
{

    /**
     * @var string
     */
    private $number;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $price;

    /**
     * @param string $number
     * @param string $name
     * @param int $quantity
     * @param float $price
     */
    public function __construct($number, $name, $quantity, $price)
    {
        $this->number = $number;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

}
