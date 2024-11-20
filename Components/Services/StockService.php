<?php

namespace MollieShopware\Components\Services;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use MollieShopware\MollieShopware;
use Psr\Log\LoggerInterface;
use Shopware\Bundle\CartBundle\CartPositionsMode;
use Shopware\Models\Order\Status;

class StockService
{
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
     * @param OrderService $orderService
     * @param Enlight_Components_Db_Adapter_Pdo_Mysql $db
     * @param LoggerInterface $logger
     */
    public function __construct($orderService, $db, $logger)
    {
        $this->orderService = $orderService;
        $this->db = $db;
        $this->logger = $logger;
    }


    /**
     * @param int $orderId
     * @param mixed $reset
     * @return void
     */
    public function updateOrderStocks($orderId, $reset = true)
    {
        $order = $this->orderService->getOrderById($orderId);
        $paymentMethodName = $order->getPayment()->getName();

        $isMolliePayment = strpos($paymentMethodName, MollieShopware::PAYMENT_PREFIX) !== false;

        if (! $isMolliePayment) {
            $this->logger->info('Order Payment does not belong to mollie', ['paymentMethodName' => $paymentMethodName]);
            return;
        }
        if ($order->getPaymentStatus()->getId() === Status::PAYMENT_STATE_COMPLETELY_PAID) {
            $this->logger->info('Order is already paid, stock updates not needed', ['orderId' => $orderId]);
            return;
        }

        $this->logger->debug('Start to reset the stocks for order', ['orderId' => $orderId]);

        foreach ($order->getDetails() as $orderDetail) {
            $articleNumber = $orderDetail->getArticleNumber();

            if ($orderDetail->getPrice() < 0) {
                $this->logger->debug('Price is lower than 0, this product was not updated in first place', ['orderId' => $orderId, 'articleNumber' => $articleNumber, 'priceNumeric' => $orderDetail->getPrice()]);
                continue;
            }
            if (! in_array($orderDetail->getMode(), [CartPositionsMode::PRODUCT, CartPositionsMode::PREMIUM_PRODUCT], true)) {
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
