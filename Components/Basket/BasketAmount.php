<?php

namespace MollieShopware\Components\Basket;

class BasketAmount
{
    /** @var float */
    private $amount;

    /** @var string */
    private $currency = '';

    /**
     * Creates a new instance of the BasketAmount DTO.
     *
     * @param $amount
     * @param $currency
     */
    public function __construct($amount = null, $currency = null)
    {
        if ($amount !== null) {
            $this->setAmount($amount);
        }

        if ($currency !== null) {
            $this->setCurrency($currency);
        }
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }
}
