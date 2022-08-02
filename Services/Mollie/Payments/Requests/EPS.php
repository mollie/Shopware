<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;

class EPS extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'eps'
        );
    }
}
