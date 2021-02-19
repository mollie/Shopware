<?php

declare(strict_types=1);


use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusMapping\PaymentTransactionMapper;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Status;

class PaymentTransactionMapperTest extends TestCase
{
    /**
     * @dataProvider statusProvider
     *
     * @covers \MollieShopware\Components\StatusMapping\PaymentTransactionMapper::mapStatus
     */
    public function testStatusMapping($status, $wantedStatus, $wantedIgnoreState)
    {
        $result = PaymentTransactionMapper::mapStatus($status);

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
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_CANCELED,
                Status::ORDER_STATE_CANCELLED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_FAILED,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_EXPIRED,
                Status::ORDER_STATE_CANCELLED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED,
                Status::ORDER_STATE_OPEN,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_OPEN,
                Status::ORDER_STATE_OPEN,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PAID,
                Status::ORDER_STATE_READY_FOR_DELIVERY,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_REFUNDED,
                null,
                true
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PENDING,
                Status::ORDER_STATE_OPEN,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED,
                null,
                true
            ],
        ];
    }
}