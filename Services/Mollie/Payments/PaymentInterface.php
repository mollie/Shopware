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
     * @param bool $enabled
     * @return void
     */
    public function setOrdersApiEnabled($enabled);

    /**
     * @return bool
     */
    public function isOrdersApiEnabled();

    /**
     * @param int $expirationDays
     * @return void
     */
    public function setExpirationDays($expirationDays);

    /**
     * @param bool $ignore
     * @return void
     */
    public function setIgnoreCheckoutURL($ignore);

    /**
     * @return bool
     */
    public function isCheckoutUrlIgnored();

    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI();

    /**
     * @return mixed[]
     */
    public function buildBodyOrdersAPI();
}
