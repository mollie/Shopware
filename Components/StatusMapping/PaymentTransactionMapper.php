<?php

declare(strict_types=1);


namespace MollieShopware\Components\StatusMapping;


use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusMapping\DataStruct\StatusTransactionStruct;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use Shopware\Models\Order\Status;

class PaymentTransactionMapper
{
    /**
     * @param string $status
     * @return StatusTransactionStruct
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
            default:
                throw new OrderStatusNotFoundException('The given status could not be mapped!');
        }

        return new StatusTransactionStruct($targetState, $ignoreState);
    }
}
