<?php

namespace MollieShopware\Services\Mollie\Payments;

use MollieShopware\Services\Mollie\Payments\Models\Payment;

interface PaymentInterface
{

    /**
     * @param Payment $payment
     * @return void
     */
    public function setPayment(Payment $payment);

    /**
     * @param int $expirationDays
     * @return void
     */
    public function setExpirationDays($expirationDays);

    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI();

    /**
     * @return mixed[]
     */
    public function buildBodyOrdersAPI();

}
