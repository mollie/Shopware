<?php

declare(strict_types=1);


namespace MollieShopware\Components\StatusMapping;


use MollieShopware\Components\Constants\PaymentStatus;
use Shopware\Models\Order\Status;

class OrderTransactionMapper
{
    /**
     * @param string $status
     * @return array
     */
    public static function mapStatus($status)
    {
        $targetState = null;
        $ignoreState = false;

        switch ($status) {
            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $targetState = Status::ORDER_STATE_COMPLETED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $targetState = Status::ORDER_STATE_CANCELLED_REJECTED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
            case PaymentStatus::MOLLIE_PAYMENT_PAID:
            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PENDING:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                # these payment status entries have no
                # impact on the order status at the moment
                $ignoreState = true;
                break;
        }

        return [
            'targetState' => $targetState,
            'ignoreState' => $ignoreState,
        ];
    }
}