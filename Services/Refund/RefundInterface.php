<?php

namespace MollieShopware\Services\Refund;

use Mollie\Api\Resources\Refund;
use MollieShopware\Models\Transaction;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

interface RefundInterface
{

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @return Refund
     */
    public function refundFullOrder(Order $order, Transaction $transaction);

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @param float $amount
     * @return Refund
     */
    public function refundPartialOrderAmount(Order $order, Transaction $transaction, $amount);

    /**
     * @param Order $order
     * @param Detail $detail
     * @param Transaction $transaction
     * @param string $orderLineID
     * @param int $quantity
     * @return Refund
     */
    public function refundPartialOrderItem(Order $order, Detail $detail, Transaction $transaction, $orderLineID, $quantity);
}
