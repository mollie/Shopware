<?php

namespace MollieShopware\Components\Helpers;

use Mollie\Api\Resources\Order;
use Shopware\Models\Payment\Payment;

class MollieRefundStatus
{

    /**
     * Gets if the provided payment has been fully refunded.
     *
     * @param Payment $payment
     * @return bool
     */
    public function isPaymentFullyRefunded(Payment $payment)
    {
        if ($payment->isPaid() && $payment->getAmountRefunded() > 0 && $payment->getAmountRemaining() <= 0) {
            return true;
        }

        return false;
    }

    /**
     * Gets if the provided payment has been at least
     * partially refunded.
     *
     * @param Payment $payment
     * @return bool
     */
    public function isPaymentPartiallyRefunded(Payment $payment)
    {
        if ($payment->isPaid() && $payment->getAmountRefunded() > 0 && $payment->getAmountRemaining() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Gets if the provided order is fully refunded.
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderFullyRefunded(Order $order)
    {
        if ($order->amountRefunded === null) {
            return false;
        }

        $orderValue = $order->amount->value;
        $refundedValue = $order->amountRefunded->value;

        # both of them are strings, but that's totally fine
        return ($orderValue === $refundedValue);
    }

    /**
     * Gets if the provided order is partially refunded.
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderPartiallyRefunded(Order $order)
    {
        if ($order->amountRefunded === null) {
            return false;
        }

        $orderValue = $order->amount->value;
        $refundedValue = $order->amountRefunded->value;

        # both of them are strings, but that's totally fine
        return ($orderValue !== $refundedValue);
    }

}
