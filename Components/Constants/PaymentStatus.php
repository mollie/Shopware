<?php

namespace MollieShopware\Components\Constants;

class PaymentStatus
{
    const MOLLIE_PAYMENT_COMPLETED = 'completed';
    const MOLLIE_PAYMENT_PAID = 'paid';
    const MOLLIE_PAYMENT_AUTHORIZED = 'authorized';
    const MOLLIE_PAYMENT_DELAYED = 'pending';
    const MOLLIE_PAYMENT_OPEN = 'open';
    const MOLLIE_PAYMENT_CANCELED = 'canceled';
    const MOLLIE_PAYMENT_EXPIRED = 'expired';
    const MOLLIE_PAYMENT_FAILED = 'failed';

    /**
     * attention!
     * mollie has no status for refunds. its always "paid", but
     * the payment itself has additional refund keys and values.
     * we still need a status for order transitions and more due to
     * the plugin architecture. so we've added our fictional status entries here!
     * these will never come from the mollie API
     */
    const MOLLIE_PAYMENT_REFUNDED = 'refunded';
    const MOLLIE_PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';
    
}
