<?php

namespace MollieShopware\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="MollieShopware\Models\TransactionRepository")
 * @ORM\Table(name="mol_sw_transaction_items")
 */
class TransactionItem
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="transaction_id", type="integer", nullable=true)
     */
    private $transactionId;

    /**
     * @var \MollieShopware\Models\Transaction
     *
     * @ORM\ManyToOne(targetEntity="\MollieShopware\Models\Transaction", inversedBy="items")
     * @ORM\JoinColumn(name="transaction_id", referencedColumnName="id")
     */
    private $transaction;

    /**
     * @var int
     *
     * @ORM\Column(name="article_id", type="integer", nullable=true)
     */
    private $articleId;

    /**
     * @var int
     *
     * @ORM\Column(name="basket_item_id", type="integer", nullable=true)
     */
    private $basketItemId;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", nullable=true)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", nullable=true)
     */
    private $type;

    /**
     * @var int
     *
     * @ORM\Column(name="quantity", type="integer", nullable=true)
     */
    private $quantity;

    /**
     * @var int
     *
     * @ORM\Column(name="unitprice", type="float", nullable=true)
     */
    private $unitPrice;

    /**
     * @var int
     *
     * @ORM\Column(name="netprice", type="float", nullable=true)
     */
    private $netPrice;

    /**
     * @var int
     *
     * @ORM\Column(name="total_amount", type="float", nullable=true)
     */
    private $totalAmount;

    /**
     * @var int
     *
     * @ORM\Column(name="vat_rate", type="decimal", nullable=true)
     */
    private $vatRate;

    /**
     * @var int
     *
     * @ORM\Column(name="vat_amount", type="float", nullable=true)
     */
    private $vatAmount;

    /**
     * @var string
     *
     * @ORM\Column(name="sku", type="string", nullable=true)
     */
    private $sku;

    /**
     * @var string
     *
     * @ORM\Column(name="image_url", type="string", nullable=true)
     */
    private $imageUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="product_url", type="string", nullable=true)
     */
    private $productUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="order_line_id", type="string", nullable=true)
     */
    private $orderLineId;

    /**
     * @return string
     *
     * @ORM\Column(name="voucher_type", type="string", nullable=true)
     */
    private $voucherType;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return TransactionItem
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param int $transactionId
     * @return TransactionItem
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * @return \MollieShopware\Models\Transaction
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param \MollieShopware\Models\Transaction $transaction
     * @return TransactionItem
     */
    public function setTransaction(\MollieShopware\Models\Transaction $transaction)
    {
        $this->transaction = $transaction;
        return $this;
    }

    /**
     * @return int
     */
    public function getArticleId()
    {
        return $this->articleId;
    }

    /**
     * @param int $articleId
     * @return TransactionItem
     */
    public function setArticleId($articleId)
    {
        $this->articleId = $articleId;
        return $this;
    }

    /**
     * @return int
     */
    public function getBasketItemId()
    {
        return $this->basketItemId;
    }

    /**
     * @param int $basketItemId
     * @return TransactionItem
     */
    public function setBasketItemId($basketItemId)
    {
        $this->basketItemId = $basketItemId;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return TransactionItem
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return TransactionItem
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     * @return TransactionItem
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @return int
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * @param int $unitPrice
     * @return TransactionItem
     */
    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    /**
     * @return int
     */
    public function getNetPrice()
    {
        return $this->netPrice;
    }

    /**
     * @param int $netPrice
     * @return TransactionItem
     */
    public function setNetPrice($netPrice)
    {
        $this->netPrice = $netPrice;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    /**
     * @param int $totalAmount
     * @return TransactionItem
     */
    public function setTotalAmount($totalAmount)
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    /**
     * @return int
     */
    public function getVatRate()
    {
        return $this->vatRate;
    }

    /**
     * @param int $vatRate
     * @return TransactionItem
     */
    public function setVatRate($vatRate)
    {
        $this->vatRate = $vatRate;
        return $this;
    }

    /**
     * @return int
     */
    public function getVatAmount()
    {
        return $this->vatAmount;
    }

    /**
     * @param int $vatAmount
     * @return TransactionItem
     */
    public function setVatAmount($vatAmount)
    {
        $this->vatAmount = $vatAmount;
        return $this;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     * @return TransactionItem
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
        return $this;
    }

    /**
     * @return string
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * @param string $imageUrl
     * @return TransactionItem
     */
    public function setImageUrl($imageUrl)
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getProductUrl()
    {
        return $this->productUrl;
    }

    /**
     * @param string $productUrl
     * @return TransactionItem
     */
    public function setProductUrl($productUrl)
    {
        $this->productUrl = $productUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderLineId()
    {
        return $this->orderLineId;
    }

    /**
     * @param string $orderLineId
     * @return TransactionItem
     */
    public function setOrderLineId($orderLineId)
    {
        $this->orderLineId = $orderLineId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVoucherType()
    {
        return $this->voucherType;
    }

    /**
     * @param mixed $voucherType
     */
    public function setVoucherType($voucherType)
    {
        $this->voucherType = $voucherType;
    }
}
