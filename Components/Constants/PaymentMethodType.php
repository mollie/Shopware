<?php

namespace MollieShopware\Components\Constants;

use MollieShopware\MollieShopware;

class PaymentMethodType
{

    const UNDEFINED = 0;
    const GLOBAL_SETTING = 1;
    const PAYMENTS_API = 2;
    const ORDERS_API = 3;

    public static $types = [
        self::UNDEFINED => 'UNDEFINED',
        self::GLOBAL_SETTING => 'GLOBAL_SETTINGS',
        self::PAYMENTS_API => 'PAYMENTS_API',
        self::ORDERS_API => 'ORDERS_API',
    ];

    /**
     * Gets if the payment method allows the payments api,
     * or requires the orders api.
     */
    public static function isPaymentsApiAllowed($paymentMethod)
    {
        $prefix = MollieShopware::PAYMENT_PREFIX;

        $onlyOrders = [
            PaymentMethod::KLARNA_PAY_LATER,
            PaymentMethod::KLARNA_PAY_NOW,
            PaymentMethod::KLARNA_SLICE_IT,
            $prefix . PaymentMethod::KLARNA_PAY_LATER,
            $prefix . PaymentMethod::KLARNA_PAY_NOW,
            $prefix . PaymentMethod::KLARNA_SLICE_IT,
            PaymentMethod::VOUCHERS,
            $prefix . PaymentMethod::VOUCHERS,
            PaymentMethod::IN3,
            $prefix . PaymentMethod::IN3,
        ];

        if (in_array($paymentMethod, $onlyOrders, true)) {
            return false;
        }

        return true;
    }

}
