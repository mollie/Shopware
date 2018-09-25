<?php

// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;


use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Shipping;

class ShipmentService
{
    /**
     *
     * @var ModelManager $modelManager
     */
    private $modelManager;


    /**
     * Constructor
     *
     * @param ModelManager $modelManager
     */
    public function __construct(ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    /**
     * Get a Shipment by it's order id
     *
     * @param int $order_id
     *
     * @return Order $order
     */
    public function getShipmentByOrderId($order_id)
    {
        // get shipment repository
        $shipping_repository = $this->modelManager->getRepository(Shipping::class);

        // find shipment
        $shipment = $shipping_repository->findOneBy([
            'orderId' => $order_id
        ]);

        return $shipment;
    }
}