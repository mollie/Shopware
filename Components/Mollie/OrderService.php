<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;

use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
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
     * Get an order by it's id
     *
     * @param int $orderId
     *
     * @return Order $order
     */
    public function getOrderById($orderId)
    {
        $order = null;

        try {
            // get order repository
            $orderRepo = $this->modelManager->getRepository(Order::class);

            // find order
            $order = $orderRepo->findOneBy([
                'id' => $orderId
            ]);
        }
        catch (Exception $ex) {
            // log error
            if ($order != null) {
                $orderRepo->addException($order, $ex);
            }
        }

        return $order;
    }

    /**
     * @return string
     */
    public function checksum()
    {
        $hash = '';
        foreach(func_get_args() as $argument){
            $hash .= $argument;
        }

        return sha1($hash);
    }
}
