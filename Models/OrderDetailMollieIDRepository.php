<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Models;

use Shopware\Components\Model\ModelRepository;
use MollieShopware\Models\Transaction;
use MollieShopware\Components\Constants\PaymentStatus;
use Exception;
use DateTime;
use Shopware\Models\Order\Order;

class OrderDetailMollieIDRepository extends ModelRepository
{

    public function Save(OrderDetailMollieID $orderDetailMollieID)
    {

        $entityManager = $this->getEntityManager();
        $entityManager->persist($orderDetailMollieID);
        $entityManager->flush();

    }

    /**
     * Gets an array of remote IDs for the order's order lines
     * which can be directly used in Mollie's Shipment API
     *
     * @param Order $order
     * @return array
     */
    public function getShipmentLines(Order $order)
    {

        /**
         * @var OrderDetailMollieID $item
         */
        $result = [];
        $items = $this->findBy(['order_id'=>$order->getId()]);

        foreach($items as $item){
            $result[] = $item->getMollieRemoteID();
        }

        return $result;

    }

}
