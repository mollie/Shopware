<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;

use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Status;
use MollieShopware\Models\OrderDetailMollieID;

class OrderService
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
     * @param int $orderId
     *
     * @return Status $status
     */
    public function getOrderById($orderId)
    {
        $status = null;

        try {
            // get shipment repository
            $statusRepo = $this->modelManager->getRepository(Status::class);

            // find status
            $status = $statusRepo->findOneBy([
                'id' => $orderId
            ]);
        }
        catch (Exception $ex) {
            // log error
            if (!empty($status)) {
                $statusRepo->addException($status, $ex);
            }
        }

        return $status;
    }

    /**
     * Get an order by Mollie's order id
     *
     * @param int $orderId
     *
     * @return OrderDetailMollieID[] $orderDetailMollieID
     */
    public function getMollieOrderDetailsByOrderId($orderId)
    {
        $shipmentLines = [];
        $mollieOrderDetails = null;

        try {
            // get mollie order detail repository
            $mollieOrderDetailsRepo = $this->modelManager->getRepository(OrderDetailMollieID::class);

            // find order
            $mollieOrderDetails = $mollieOrderDetailsRepo->findBy([
                'orderID' => $orderId
            ]);
        }
        catch (Exception $ex) {
            // log error
            if (!empty($mollieOrderDetails)) {
                $mollieOrderDetailsRepo->addException($mollieOrderDetails, $ex);
            }
        }

        // get shipment lines
        if (!empty($mollieOrderDetails)) {
            foreach ($mollieOrderDetails as $mollieOrderDetail) {
                $shipmentLines[] = $mollieOrderDetail->getMollieRemoteID();
            }
        }

        return $shipmentLines;
    }

    public function checksum()
    {

        $hash = '';
        foreach(func_get_args() as $argument){
            $hash .= $argument;
        }

        return sha1($hash);

    }
}
