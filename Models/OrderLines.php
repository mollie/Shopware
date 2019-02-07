<?php

	// Mollie Shopware Plugin Version: 1.3.15

namespace MollieShopware\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="MollieShopware\Models\OrderLinesRepository")
 * @ORM\Table(name="mol_sw_orderlines")
 */
class OrderLines
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="mollie_orderline_id", type="string", nullable=false)
     */
    private $mollieOrderlineId;

    /**
     * @var integer
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId;


    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }


    public function setMollieOrderlineId($mollieOrderlineId)
    {
        $this->mollieOrderlineId = $mollieOrderlineId;
    }

    public function getMollieOrderlineId()
    {
        return $this->mollieOrderlineId;
    }


    public function setOrderID($orderId)
    {
        $this->orderId = $orderId;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

}
