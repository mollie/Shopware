<?php

// Mollie Shopware Plugin Version: 1.4.6

namespace MollieShopware\Models;

use Shopware\Components\Model\ModelRepository;

class OrderLinesRepository extends ModelRepository
{
    /**
     * Save the order line
     *
     * @param OrderLines $mollieOrderLines
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(OrderLines $mollieOrderLines)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getEntityManager();

        $entityManager->persist($mollieOrderLines);
        $entityManager->flush();
    }

    /**
     * Gets an array of remote IDs for the order's order lines
     * which can be directly used in Mollie's Shipment API
     *
     * @param \Shopware\Models\Order\Order $order
     * @return array
     */
    public function getShipmentLines(\Shopware\Models\Order\Order $order)
    {
        $result = [];

        /** @var OrderLines[] $items */
        $items = $this->findBy(['orderId' => $order->getId()]);

        foreach($items as $item) {
            $result[] = [
                'id' => $item->getMollieOrderlineId()
            ];
        }

        return $result;
    }
}