<?php

declare(strict_types=1);

namespace MollieShopware\Services\Refund;

use MollieShopware\Models\Transaction;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

interface RefundInterface
{

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @return mixed
     */
    public function refundFullOrder(Order $order, Transaction $transaction);

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @param float $amount
     * @return mixed
     */
    public function refundPartialOrderAmount(Order $order, Transaction $transaction, $amount);

    /**
     * @param Order $order
     * @param Detail $detail
     * @param Transaction $transaction
     * @param int $orderLineID
     * @param int $quantity
     * @return mixed
     */
    public function refundPartialOrderItem(Order $order, Detail $detail, Transaction $transaction, $orderLineID, $quantity);

}
