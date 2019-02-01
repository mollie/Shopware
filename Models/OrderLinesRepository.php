<?php

	// Mollie Shopware Plugin Version: 1.3.14

namespace MollieShopware\Models;

use Shopware\Components\Model\ModelRepository;
use MollieShopware\Models\Transaction;
use MollieShopware\Components\Constants\PaymentStatus;
use Exception;
use DateTime;
use Shopware\Models\Order\Order;

class OrderLinesRepository extends ModelRepository
{

    public function Save(OrderLines $mollieOrderLines)
    {

        $entityManager = $this->getEntityManager();
        $entityManager->persist($mollieOrderLines);
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
         * @var OrderLines $item
         */
        $result = [];
        $items = $this->findBy(['orderId' => $order->getId()]);

        foreach($items as $item){
            $result[] = [
                'id' => $item->getMollieOrderlineId()
            ];
        }

        return $result;

    }

}
