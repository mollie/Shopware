<?php

namespace MollieShopware\Components\Constants;

use MollieShopware\MollieShopware;

class ShopwarePaymentMethod
{

    /**
     *
     */
    const APPLEPAYDIRECT = MollieShopware::PAYMENT_PREFIX . PaymentMethod::APPLEPAY_DIRECT;

    /**
     *
     */
    const APPLEPAY = MollieShopware::PAYMENT_PREFIX . PaymentMethod::APPLE_PAY;
}
