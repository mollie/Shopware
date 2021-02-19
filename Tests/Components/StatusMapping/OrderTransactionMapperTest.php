<?php

declare(strict_types=1);


use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusMapping\OrderTransactionMapper;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Status;

class OrderTransactionMapperTest extends TestCase
{
    /**
     * @dataProvider statusProvider
     *
     * @covers MollieShopware\Components\StatusMapping\OrderTransactionMapper::mapStatus
     */
    public function testStatusMapping($status, $wantedStatus, $wantedIgnoreState)
    {
        $result = OrderTransactionMapper::mapStatus($status);

        $this->assertSame($result->getTargetStatus(), $wantedStatus);
        $this->assertSame($result->isIgnoreState(), $wantedIgnoreState);
    }

    /**
     * @return array[]
     */
    public function statusProvider()
    {
        return [
            [
                PaymentStatus::MOLLIE_PAYMENT_COMPLETED,
                Status::ORDER_STATE_COMPLETED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_CANCELED,
                Status::ORDER_STATE_CANCELLED_REJECTED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_FAILED,
                Status::ORDER_STATE_CANCELLED_REJECTED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_EXPIRED,
                Status::ORDER_STATE_CANCELLED_REJECTED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_OPEN,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PAID,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_REFUNDED,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PENDING,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED,
                null,
                true
            ],
        ];
    }
}