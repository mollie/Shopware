<?php

// Mollie Shopware Plugin Version: 1.4

namespace MollieShopware\Components\Constants;

class PaymentStatus
{
    const MOLLIE_PAYMENT_COMPLETED = 'completed';
    const MOLLIE_PAYMENT_PAID = 'paid';
    const MOLLIE_PAYMENT_AUTHORIZED = 'authorized';
    const MOLLIE_PAYMENT_DELAYED = 'pending';
    const MOLLIE_PAYMENT_CANCELED = 'canceled';
    const MOLLIE_PAYMENT_FAILED = 'failed';
}