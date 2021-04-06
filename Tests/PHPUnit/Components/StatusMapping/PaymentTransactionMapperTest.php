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
                Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_FAILED,
                Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_EXPIRED,
                Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                false
            ],
            // This payment status needs the a booted Shopware instance
//            [
//                PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED,
//                null,
//                false
//            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_OPEN,
                Status::ORDER_STATE_OPEN,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PAID,
                Status::PAYMENT_STATE_COMPLETELY_PAID,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_REFUNDED,
                Status::PAYMENT_STATE_RE_CREDITING,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PENDING,
                Status::PAYMENT_STATE_DELAYED,
                false
            ],
            [
                PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED,
                Status::PAYMENT_STATE_RE_CREDITING,
                false
            ],
        ];
    }
}
