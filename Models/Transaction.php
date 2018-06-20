<?php

	// Mollie Shopware Plugin Version: 1.1.0.4

namespace MollieShopware\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="MollieShopware\Models\TransactionRepository")
 * @ORM\Table(name="mollie_shopware_transactions")
 */
class Transaction
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="user_id", type="integer", nullable=false)
     */
    private $userId;

    /**
     * @var string
     *
     * @ORM\Column(name="quote_number", type="string", nullable=true)
     */
    private $quoteNumber;

    /**
     * @var integer
     *
     * @ORM\Column(name="payment_id", type="integer", nullable=false)
     */
    private $paymentId;

    /**
     * @var string
     *
     * @ORM\Column(name="transaction_id", type="string", nullable=true)
     */
    private $transactionId;

    /**
     * @var string
     *
     * @ORM\Column(name="session_id", type="string", length=70, nullable=false)
     */
    private $sessionId;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="string", nullable=true)
     */
    private $token;

    /**
     * @var string
     *
     * @ORM\Column(name="signature", type="string", nullable=true)
     */
    private $signature;

    /**
     * @var integer
     *
     * @ORM\Column(name="status", type="integer", nullable=true)
     */
    private $status;

    /**
     * @var float
     *
     * @ORM\Column(name="amount", type="float", nullable=true)
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="currency", type="string", nullable=true)
     */
    private $currency;

    /**
     * @var string
     *
     * @ORM\Column(name="order_number", type="string", nullable=true)
     */
    private $orderNumber;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @var \DateTime $updatedAt
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="exceptions", type="json_array", nullable=true)
     */
    private $exceptions;

    /**
     * @var string
     *
     * @ORM\Column(name="session", type="text", nullable=true)
     */
    private $session = '';

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getQuoteNumber()
    {
        return $this->quoteNumber;
    }

    /**
     * @param string $quoteNumber
     */
    public function setQuoteNumber($quoteNumber)
    {
        $this->quoteNumber = $quoteNumber;
        return $this;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return int
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * @param int $paymentId
     */
    public function setPaymentId($paymentId)
    {
        $this->paymentId = $paymentId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param string $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @param string $sessionId
     */
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
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
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @param string $orderNumber
     */
    public function setOrderNumber($orderNumber)
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return array
     */
    public function getExceptions()
    {
        return empty($this->exceptions) ? [] : $this->exceptions;
    }

    /**
     * @param array $exception
     */
    public function setExceptions(array $exceptions = [])
    {
        $this->exceptions = $exceptions;
    }


    public function setSerializedSession($session)
    {
        $this->session = $session;
    }

    public function getSerializedSession($default = '')
    {
        return empty($this->session) ? $default : $this->session;
    }

    public function getQueryString()
    {

        return implode('&', [

            'id=' . $this->getID(),
            'cs=' . $this->getChecksum(),

        ]);

    }

    public function getChecksum()
    {

        /*
         * We generate a checksum based on the transaction ID, the
         * Mollie API key and a secret which is not totally secret
         * as it is contained in our repository.
         */
        $config = shopware()->container()
            ->get('mollie_shopware.config');

        $local_key = $config->ApiKey();

        return $this->getId() . '.' . chunk_split(substr(sha1($local_key . $this->getId() . '!yqHa9W!3Hm$6UL$b2hXARr=Ux%SN^L!G7%BRqCaXGYrnEZL&m#Bqg%P+W85cExQa-ZEKXj4P_WRv45aCzHYrYkkbqCDRmHSHa2upJvSAZVGzfEKc*eJCkr8qu2DHgu&zU$PK9hdCx$gmt#vNz9se*sLmLwf$&Wn@^a-e$xGnb*tL4BgZ6CE2Y-EPG!=_@FtEXxeaL3S*qxwBaC%WGXGh9&nSysaE67tH#=%26wnD%tW7F6Hap3uFLFzqVy$zx*7'), 0, 12), 4, '-');

    }

    public function getOrder()
    {



    }

}
