<?php

namespace MollieShopware\Tests\Components\StatusConverter;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusConverter\OrderStatusConverter;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Status;

class OrderStatusConverterTest extends TestCase
{

    /**
     *
     */
    public const CUSTOM_STATUS_SHIPPING = 99;


    /**
     * This test verifies that our Mollie Payment status is
     * correctly converted into the matching Shopware Order Status
     * Here are overviews about the status changes in Mollie:
     * - https://docs.mollie.com/payments/status-changes
     * - https://docs.mollie.com/orders/status-changes
     *
     * @dataProvider getStatusMappings
     *
     * @param $status
     * @param $expectedStatus
     * @throws \MollieShopware\Exceptions\OrderStatusNotFoundException
     */
    public function testStatusMapping($status, $expectedStatus)
    {
        $mapper = new OrderStatusConverter(self::CUSTOM_STATUS_SHIPPING);

        $result = $mapper->getShopwareOrderStatus($status);

        # if we have no target state, that means we
        # just ignore that state
        $expectedIgnoreState = ($expectedStatus === null);

        $this->assertEquals($expectedStatus, $result->getTargetStatus());
        $this->assertEquals($expectedIgnoreState, $result->isIgnoreState());
    }

    /**
     * @return array[]
     */
    public function getStatusMappings()
    {
        return [
            'mollie_open' => [PaymentStatus::MOLLIE_PAYMENT_OPEN, Status::ORDER_STATE_OPEN],
            'mollie_pending' => [PaymentStatus::MOLLIE_PAYMENT_PENDING, null],
            'mollie_authorized' => [PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, null],
            # ------------------------------------------------------------------------------------------------------
            'mollie_paid' => [PaymentStatus::MOLLIE_PAYMENT_PAID, Status::ORDER_STATE_COMPLETED],
            'mollie_completed' => [PaymentStatus::MOLLIE_PAYMENT_COMPLETED, Status::ORDER_STATE_COMPLETED],
            # ------------------------------------------------------------------------------------------------------
            'mollie_shipping' => [PaymentStatus::MOLLIE_PAYMENT_SHIPPING, self::CUSTOM_STATUS_SHIPPING],
            # ------------------------------------------------------------------------------------------------------
            'mollie_canceled' => [PaymentStatus::MOLLIE_PAYMENT_CANCELED, Status::ORDER_STATE_CANCELLED_REJECTED],
            'mollie_failed' => [PaymentStatus::MOLLIE_PAYMENT_FAILED, Status::ORDER_STATE_CANCELLED_REJECTED],
            'mollie_expired' => [PaymentStatus::MOLLIE_PAYMENT_EXPIRED, Status::ORDER_STATE_CANCELLED_REJECTED],
            # ------------------------------------------------------------------------------------------------------
            'mollie_refunded' => [PaymentStatus::MOLLIE_PAYMENT_REFUNDED, null],
            'mollie_partially_refunded' => [PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED, null],
        ];
    }
}
