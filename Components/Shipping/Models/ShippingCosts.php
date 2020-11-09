<?php

namespace MollieShopware\Components\Shipping\Models;


class ShippingCosts
{

    /**
     * @var float
     */
    private $unitPrice;

    /**
     * @var float
     */
    private $unitPriceNet;

    /**
     * @var int
     */
    private $taxRate;

    /**
     * BasketShipping constructor.
     * @param float $unitPrice
     * @param float $unitPriceNet
     * @param int $taxRate
     */
    public function __construct($unitPrice, $unitPriceNet, $taxRate)
    {
        $this->unitPrice = $unitPrice;
        $this->unitPriceNet = $unitPriceNet;
        $this->taxRate = $taxRate;
    }

    /**
     * @return float
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * @return float
     */
    public function getUnitPriceNet()
    {
        return $this->unitPriceNet;
    }

    /**
     * @return int
     */
    public function getTaxRate()
    {
        return $this->taxRate;
    }

}