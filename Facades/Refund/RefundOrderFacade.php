<?php

namespace MollieShopware\Facades\Refund;

use Mollie\Api\Resources\Refund;
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
     * @throws TransactionNotFoundException
     * @throws OrderNotFoundException
     * @return Refund
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

        # if we have provided a custom amount
        # and this amount is the same as the order amount, then we want to do a FULL refund.
        # some payment methods have a check if partial refunds are allowed (SOFORT)....but this is NO partial refund
        # and therefore we MUST NOT invoke a partial refund accidentally.
        if (!empty($customAmount) && $order->getInvoiceAmount() === $customAmount) {
            $customAmount = null;
        }


        if (empty($customAmount)) {
            return $this->refundService->refundFullOrder($order, $transaction);
        } else {
            return $this->refundService->refundPartialOrderAmount($order, $transaction, $customAmount);
        }
    }
}
