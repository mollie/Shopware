<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Exceptions\ApiNotSupportedException;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;

class BancomatPay extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            PaymentMethod::BANCOMAT_PAY
        );
    }
}
