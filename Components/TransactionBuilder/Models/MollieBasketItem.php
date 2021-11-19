<?php

namespace MollieShopware\Components\TransactionBuilder\Models;

use MollieShopware\Models\Voucher\VoucherType;
use MollieShopware\Services\Mollie\Payments\Requests\Voucher;

class MollieBasketItem
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $articleID;

    /**
     * @var string
     */
    private $orderNumber;

    /**
     * @var int
     */
    private $esdArticle;

    /**
     * @var int
     */
    private $mode;

    /**
     * @var string
     */
    private $name;

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
    private $quantity;

    /**
     * @var int
     */
    private $taxRate;

    /**
     * @var bool
     */
    private $isGrossPrice;

    /**
     * @var string
     */
    private $voucherType;


    /**
     * @param int $id
     * @param int $articleID
     * @param string $orderNumber
     * @param int $esdArticle
     * @param int $mode
     * @param string $name
     * @param float $unitPrice
     * @param float $unitPriceNet
     * @param int $quantity
     * @param int $taxRate
     * @param string $voucherType
     */
    public function __construct($id, $articleID, $orderNumber, $esdArticle, $mode, $name, $unitPrice, $unitPriceNet, $quantity, $taxRate, $voucherType)
    {
        $this->id = $id;
        $this->articleID = $articleID;
        $this->orderNumber = $orderNumber;
        $this->esdArticle = $esdArticle;
        $this->mode = $mode;
        $this->name = $name;
        $this->unitPrice = $unitPrice;
        $this->unitPriceNet = $unitPriceNet;
        $this->quantity = $quantity;
        $this->taxRate = $taxRate;
        $this->voucherType = $voucherType;

        $this->isGrossPrice = true;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getArticleID()
    {
        return $this->articleID;
    }

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @return int
     */
    public function getEsdArticle()
    {
        return $this->esdArticle;
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @return int
     */
    public function getTaxRate()
    {
        return $this->taxRate;
    }

    /**
     * @param $isGrossPrice
     */
    public function setIsGrossPrice($isGrossPrice)
    {
        $this->isGrossPrice = $isGrossPrice;
    }

    /**
     * @return bool
     */
    public function isGrossPrice()
    {
        return $this->isGrossPrice;
    }

    /**
     * @return string
     */
    public function getVoucherType()
    {
        $allowed = [
            VoucherType::NONE,
            VoucherType::ECO,
            VoucherType::MEAL,
            VoucherType::GIFT
        ];

        if (!in_array($this->voucherType, $allowed)) {
            return VoucherType::NONE;
        }

        return $this->voucherType;
    }

}
