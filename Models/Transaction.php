<?php

// Mollie Shopware Plugin Version: 1.4.9

namespace MollieShopware\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="MollieShopware\Models\TransactionRepository")
 * @ORM\Table(name="mol_sw_transactions")
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
     * @ORM\Column(name="transaction_id", type="string", nullable=true)
     */
    private $transactionId;

    /**
     * @var integer
     *
     * @ORM\Column(name="order_id", type="integer", nullable=true)
     */
    private $orderId;

    /**
     * @var string
     *
     * @ORM\Column(name="mollie_id", type="string", nullable=true)
     */
    private $mollieId;

    /**
     * @var string
     *
     * @ORM\Column(name="mollie_payment_id", type="string", nullable=true)
     */
    private $molliePaymentId;

    /**
     * @var string
     *
     * @ORM\Column(name="session_id", type="string", nullable=true)
     */
    private $sessionId;

    /**
     * @var string
     *
     * @ORM\Column(name="ordermail_variables", type="text", nullable=true)
     */
    private $ordermailVariables;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    public function getMollieId()
    {
        return $this->mollieId;
    }

    public function setMollieId($mollieId)
    {
        $this->mollieId = $mollieId;
    }

    public function getMolliePaymentId()
    {
        return $this->molliePaymentId;
    }

    public function setMolliePaymentId($molliePaymentId)
    {
        $this->molliePaymentId = $molliePaymentId;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function setOrdermailVariables($ordermailVariables)
    {
        $this->ordermailVariables = $ordermailVariables;
    }

    public function getOrdermailVariables()
    {
        return $this->ordermailVariables;
    }
}