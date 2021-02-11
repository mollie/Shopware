<?php

declare(strict_types=1);


namespace MollieShopware\Components\StatusMapping;


use MollieShopware\Components\Constants\PaymentStatus;
use Shopware\Models\Order\Status;

class PaymentTransactionMapper
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
            case PaymentStatus::MOLLIE_PAYMENT_PAID:
                $targetState = Status::ORDER_STATE_READY_FOR_DELIVERY;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
            case PaymentStatus::MOLLIE_PAYMENT_PENDING:
            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $targetState = Status::ORDER_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
                $targetState = Status::ORDER_STATE_CANCELLED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
                $ignoreState = true;
                break;
        }

        return [
            'targetState' => $targetState,
            'ignoreState' => $ignoreState,
        ];
    }
}