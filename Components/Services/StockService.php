<?php

namespace MollieShopware\Components\Services;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use MollieShopware\Components\Config;
use Psr\Log\LoggerInterface;

class StockService
{
    /**
     * cannot use Shopware\Bundle\CartBundle\CartPositionsMode constants, they dont exists in 5.7.3 and below
     */
    const PRODUCT = 0;
    const PREMIUM_PRODUCT = 1;
    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    private $db;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param OrderService $orderService
     * @param Enlight_Components_Db_Adapter_Pdo_Mysql $db
     * @param LoggerInterface $logger
     * @param mixed $config
     */
    public function __construct($orderService, $config, $db, $logger)
    {
        $this->orderService = $orderService;
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
    }


    /**
     * @param int $orderId
     * @param mixed $reset
     * @return void
     */
    public function updateOrderStocks($orderId, $reset = true)
    {
        if (!$this->config->reduceStockOnPayment()) {
            $this->logger->debug('Reduce stock on payment config is disabled');
            return;
        }
        $order = $this->orderService->getOrderById($orderId);

        $this->logger->debug('Start to reset the stocks for order', ['orderId' => $orderId]);

        foreach ($order->getDetails() as $orderDetail) {
            $articleNumber = $orderDetail->getArticleNumber();

            if ($orderDetail->getPrice() < 0) {
                $this->logger->debug('Price is lower than 0, this product was not updated in first place', ['orderId' => $orderId, 'articleNumber' => $articleNumber, 'priceNumeric' => $orderDetail->getPrice()]);
                continue;
            }
            if (! in_array($orderDetail->getMode(), [self::PRODUCT, self::PREMIUM_PRODUCT], true)) {
                $this->logger->debug('Order detail does not have a regular product', ['orderId' => $orderId, 'articleNumber' => $articleNumber, 'mode' => $orderDetail->getMode()]);
                continue;
            }
            $quantity = $orderDetail->getQuantity();

            $this->runStockUpdate($quantity, $articleNumber, $reset);
        }

        $this->logger->debug('Resetted all product stocks', ['orderId' => $orderId]);
    }

    private function runStockUpdate($quantity, $articleNumber, $reset = true)
    {
        $sqL = 'UPDATE s_articles_details
             SET instock = instock + :quantity
             WHERE ordernumber = :number';

        if ($reset === false) {
            $sqL = 'UPDATE s_articles_details
             SET instock = instock - :quantity
             WHERE ordernumber = :number';
        }

        $this->db->executeUpdate($sqL, [':quantity' => $quantity, ':number' => $articleNumber]);
    }
}
