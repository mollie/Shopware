<?php

	// Mollie Shopware Plugin Version: 1.3.6

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
     * @ORM\Column(name="order_id", type="integer", nullable=true)
     */
    private $order_id;

    /**
     * @var string
     *
     * @ORM\Column(name="mollie_id", type="string", nullable=true)
     */
    private $mollie_id;



    public function getId()                 { return $this->id; }
    public function setId($id)              { $this->id = $id; }

    public function getOrderId()            { return $this->order_id; }
    public function setOrderId($order_id)   { $this->order_id = $order_id; }

    public function getMollieId()           { return $this->mollie_id; }
    public function setMollieId($mollie_id) { $this->mollie_id = $mollie_id; }

}
