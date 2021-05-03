<?php

namespace MollieShopware\Services\Mollie\Payments\Models;


class PaymentLineItem
{

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var float
     */
    private $unitPrice;

    /**
     * @var float
     */
    private $totalAmount;

    /**
     * @var float
     */
    private $vatRate;

    /**
     * @var float
     */
    private $vatAmount;

    /**
     * @var string
     */
    private $sku;

    /**
     * @var string
     */
    private $imageUrl;

    /**
     * @var string
     */
    private $productUrl;

    /**
     * @var string
     */
    private $metadata;

    /**
     * @param string $type
     * @param string $name
     * @param int $quantity
     * @param string $currency
     * @param float $unitPrice
     * @param float $totalAmount
     * @param float $vatRate
     * @param float $vatAmount
     * @param string $sku
     * @param string $imageUrl
     * @param string $productUrl
     * @param string $metadata
     */
    public function __construct($type, $name, $quantity, $currency, $unitPrice, $totalAmount, $vatRate, $vatAmount, $sku, $imageUrl, $productUrl, $metadata)
    {
        $this->type = $type;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->currency = $currency;
        $this->unitPrice = $unitPrice;
        $this->totalAmount = $totalAmount;
        $this->vatRate = $vatRate;
        $this->vatAmount = $vatAmount;
        $this->sku = $sku;
        $this->imageUrl = $imageUrl;
        $this->productUrl = $productUrl;
        $this->metadata = $metadata;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
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
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
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
    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    /**
     * @return float
     */
    public function getVatRate()
    {
        return $this->vatRate;
    }

    /**
     * @return float
     */
    public function getVatAmount()
    {
        return $this->vatAmount;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @return string
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * @return string
     */
    public function getProductUrl()
    {
        return $this->productUrl;
    }

    /**
     * @return string
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

}
