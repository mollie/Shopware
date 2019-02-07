<?php

	// Mollie Shopware Plugin Version: 1.3.15

namespace Mollie\Api\Types;

class MandateMethod
{
    const DIRECTDEBIT = "directdebit";
    const CREDITCARD = "creditcard";
    public static function getForFirstPaymentMethod($firstPaymentMethod)
    {
        if ($firstPaymentMethod === static::CREDITCARD) {
            return static::CREDITCARD;
        }
        return static::DIRECTDEBIT;
    }
}
