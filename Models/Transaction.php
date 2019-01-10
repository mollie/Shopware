<?php

	// Mollie Shopware Plugin Version: 1.3.13

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
    private $transaction_id;

    /**
     * @var integer
     *
     * @ORM\Column(name="order_id", type="integer", nullable=true)
     */
    private $order_id;

    /**
     * @var string
     *
     * @ORM\Column(name="mollie_id", type="string", nullable=true)
     */
    private $mollie_id;

    /**
     * @var string
     *
     * @ORM\Column(name="ordermail_variables", type="text", nullable=true)
     */
    private $ordermail_variables;

    public function getID()
    {
        return $this->id;
    }

    public function setID($id)
    {
        $this->id = $id;
    }

    public function getTransactionID()
    {
        return $this->transaction_id;
    }

    public function setTransactionID($transaction_id)
    {
        $this->transaction_id = $transaction_id;
    }

    public function getOrderID()
    {
        return $this->order_id;
    }

    public function setOrderID($order_id)
    {
        $this->order_id = $order_id;
    }

    public function getMollieID()
    {
        return $this->mollie_id;
    }

    public function setMollieID($mollie_id)
    {
        $this->mollie_id = $mollie_id;
    }

    public function setOrdermailVariables($ordermail_variables)
    {
        $this->ordermail_variables = $ordermail_variables;
    }

    public function getOrdermailVariables()
    {
        return $this->ordermail_variables;
    }
}
