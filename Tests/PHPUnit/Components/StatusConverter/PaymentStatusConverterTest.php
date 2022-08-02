<?php

namespace MollieShopware\Tests\Components\StatusConverter;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusConverter\PaymentStatusConverter;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Status;

class PaymentStatusConverterTest extends TestCase
{

    /**
     *
     */
    public const CUSTOM_STATUS_AUTHORIZED = 99;


    /**
     * This test verifies that our Mollie Payment status is
     * correctly converted into the matching Shopware Payment Status
     * Here are overviews about the status changes in Mollie:
     * - https://docs.mollie.com/payments/status-changes
     * - https://docs.mollie.com/orders/status-changes
     *
     * @dataProvider getStatusMappings
     *
     * @param $status
     * @param $expectedStatus
     * @throws \MollieShopware\Exceptions\PaymentStatusNotFoundException
     */
    public function testStatusMapping($status, $expectedStatus)
    {
        $converter = new PaymentStatusConverter(self::CUSTOM_STATUS_AUTHORIZED);

        $result = $converter->getShopwarePaymentStatus($status);

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
            'mollie_open' => [PaymentStatus::MOLLIE_PAYMENT_OPEN, Status::PAYMENT_STATE_OPEN],
            'mollie_pending' => [PaymentStatus::MOLLIE_PAYMENT_PENDING, Status::PAYMENT_STATE_DELAYED],
            'mollie_authorized' => [PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED, self::CUSTOM_STATUS_AUTHORIZED],
            # ------------------------------------------------------------------------------------------------------
            'mollie_paid' => [PaymentStatus::MOLLIE_PAYMENT_PAID, Status::PAYMENT_STATE_COMPLETELY_PAID],
            'mollie_completed' => [PaymentStatus::MOLLIE_PAYMENT_COMPLETED, Status::PAYMENT_STATE_COMPLETELY_PAID],
            # ------------------------------------------------------------------------------------------------------
            'mollie_shipping' => [PaymentStatus::MOLLIE_PAYMENT_SHIPPING, null],
            # ------------------------------------------------------------------------------------------------------
            'mollie_canceled' => [PaymentStatus::MOLLIE_PAYMENT_CANCELED, Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED],
            'mollie_failed' => [PaymentStatus::MOLLIE_PAYMENT_FAILED, Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED],
            'mollie_expired' => [PaymentStatus::MOLLIE_PAYMENT_EXPIRED, Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED],
            # ------------------------------------------------------------------------------------------------------
            'mollie_refunded' => [PaymentStatus::MOLLIE_PAYMENT_REFUNDED, Status::PAYMENT_STATE_RE_CREDITING],
            'mollie_partially_refunded' => [PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED, Status::PAYMENT_STATE_RE_CREDITING],
        ];
    }
}
