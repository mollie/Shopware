<?php

declare(strict_types=1);


namespace MollieShopware\Components\StatusMapping;


use MollieShopware\Components\Config;
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
                $targetState = Status::PAYMENT_STATE_COMPLETELY_PAID;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
                $targetState = Status::ORDER_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                Shopware()->Container()->get('mollie_shopware.config')->getAuthorizedPaymentStatusId();
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PENDING:
                $targetState = Status::PAYMENT_STATE_DELAYED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
                $targetState = Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $targetState = Status::PAYMENT_STATE_RE_CREDITING;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $ignoreState = true;
                break;

            default:
                throw new OrderStatusNotFoundException('The given status could not be mapped!');
        }

        return new StatusTransactionStruct($targetState, $ignoreState);
    }
}
