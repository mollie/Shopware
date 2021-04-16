<?php

namespace MollieShopware\Components\StatusConverter;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusConverter\DataStruct\StatusTransactionStruct;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use Shopware\Models\Order\Status;

class OrderStatusConverter
{


    /**
     * @param string $molliePaymentStatus
     * @return StatusTransactionStruct
     * @throws PaymentStatusNotFoundException
     */
    public function getShopwareOrderStatus($molliePaymentStatus)
    {
        $targetState = null;

        switch ($molliePaymentStatus) {

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
                $targetState = Status::ORDER_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PAID:
            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $targetState = Status::ORDER_STATE_COMPLETED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $targetState = Status::ORDER_STATE_CANCELLED_REJECTED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PENDING:
            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                break;

            default:
                throw new PaymentStatusNotFoundException('Unable to get Shopware Order Status for Mollie Payment Status: ' . $molliePaymentStatus);
        }


        $ignoreState = ($targetState === null);

        return new StatusTransactionStruct($targetState, $ignoreState);
    }
}
