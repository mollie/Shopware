<?php

namespace MollieShopware\Facades\Refund;

use MollieShopware\Components\Services\OrderService;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Models\Transaction;
use MollieShopware\Services\Refund\RefundInterface;
use Shopware\Models\Order\Order;


class RefundOrderFacade
{

    /**
     * @var RefundInterface
     */
    private $refundService;

    /**
     * @var OrderService
     */
    private $orderService;


    /**
     * @param \MollieShopware\Services\Refund\RefundInterface $refundService
     * @param \MollieShopware\Components\Services\OrderService $orderService
     */
    public function __construct(RefundInterface $refundService, OrderService $orderService)
    {
        $this->refundService = $refundService;
        $this->orderService = $orderService;
    }

    /**
     * @param string $orderNumber
     * @param float $customAmount
     * @throws OrderNotFoundException
     * @throws TransactionNotFoundException
     */
    public function refundOrder($orderNumber, $customAmount)
    {
        $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

        if (!$order instanceof Order) {
            throw new OrderNotFoundException('Order with number: ' . $orderNumber . ' not found in Shopware');
        }

        $transaction = $this->orderService->getOrderTransactionByNumber($orderNumber);

        if (!$transaction instanceof Transaction) {
            throw new TransactionNotFoundException('Transaction for Order Number: ' . $orderNumber . ' not found!');
        }
        
        if (empty($customAmount)) {
            $this->refundService->refundFullOrder($order, $transaction);
        } else {
            $this->refundService->refundPartialOrderAmount($order, $transaction, $customAmount);
        }
    }

}