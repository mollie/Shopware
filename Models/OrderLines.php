<?php

	// Mollie Shopware Plugin Version: 1.4.1

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
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer $mollieOrderlineId
     *
     * @ORM\Column(name="mollie_orderline_id", type="string", nullable=false)
     */
    private $mollieOrderlineId;

    /**
     * @var integer $orderId
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId;

    /**
     * Set the ID for the order line
     *
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get the ID of the order line
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Set Mollie's order line ID
     *
     * @param $mollieOrderlineId
     */
    public function setMollieOrderlineId($mollieOrderlineId)
    {
        $this->mollieOrderlineId = $mollieOrderlineId;
    }

    /**
     * Get Mollie's order line ID
     *
     * @return int
     */
    public function getMollieOrderlineId()
    {
        return $this->mollieOrderlineId;
    }

    /**
     * Set the order ID for the order line
     *
     * @param $orderId
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Get the order ID for the order line
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

}