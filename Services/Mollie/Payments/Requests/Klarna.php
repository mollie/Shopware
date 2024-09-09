<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Exceptions\ApiNotSupportedException;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;

class Klarna extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            PaymentMethod::KLARNA_ONE
        );
    }

    /**
     * @throws ApiNotSupportedException
     * @return mixed[]|void
     */
    public function buildBodyPaymentsAPI()
    {
        throw new ApiNotSupportedException('Klarna One does not support the Payments API!');
    }
}
