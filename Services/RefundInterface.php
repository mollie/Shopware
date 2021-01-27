<?php

declare(strict_types=1);

namespace MollieShopware\Services;

use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment as MolliePayment;
use MollieShopware\Models\Transaction;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

interface RefundInterface
{
    public function refundOrderAmount(Order $order, Transaction $transaction, float $customAmount = null);

    public function refundOrder(Order $order, MollieOrder $mollieOrder);

    public function partialRefundOrder(Order $order, Detail $detail, MollieOrder $mollieOrder, OrderLine $orderLine, $quantity = 1);

    public function refundPayment(Order $order, MolliePayment $molliePayment);

    public function partialRefundPayment(Order $order, MolliePayment $molliePayment, $amountToRefund);

    public function processRefund(Order $order);

    public function updateRefundedItemsOnOrderDetail($detail, $quantity);
}