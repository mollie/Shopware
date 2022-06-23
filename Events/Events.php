<?php

namespace MollieShopware\Events;

interface Events
{

    /**
     *
     */
    const UPDATE_ORDER_PAYMENT_STATUS = 'Mollie_OrderUpdate_PaymentStatus_FilterResult';

    /**
     *
     */
    const UPDATE_ORDER_STATUS = 'Mollie_OrderUpdate_OrderStatus_FilterResult';

    /**
     *
     */
    const APPLEPAY_DIRECT_GET_SHIPPINGS = 'Mollie_ApplePayDirect_getShippings_FilterResult';

    /**
     *
     */
    const APPLEPAY_DIRECT_SET_SHIPPING = 'Mollie_ApplePayDirect_setShipping_FilterResult';

    /**
     *
     */
    const WEBHOOK_RECEIVED = 'Mollie_WebhookReceived';
}
