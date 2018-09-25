<?php

// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="MollieShopware\Models\OrderDetailMollieIDRepository")
 * @ORM\Table(name="order_detail_mollie_ids")
 */
class OrderDetailMollieID
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
     * @ORM\Column(name="mollie_remote_id", type="string", nullable=false)
     */
    private $mollieRemoteID;

    /**
     * @var integer
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderID;


    public function setID($id)
    {
        $this->id = $id;
    }

    public function getID()
    {
        return $this->id;
    }


    public function setMollieRemoteID($mollieRemoteID)
    {
        $this->mollieRemoteID = $mollieRemoteID;
    }

    public function getMollieRemoteID()
    {
        return $this->mollieRemoteID;
    }


    public function setOrderID($orderID)
    {
        $this->orderID = $orderID;
    }

    public function getOrderId()
    {
        return $this->orderID;
    }

}
