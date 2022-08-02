<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Exceptions\ApiNotSupportedException;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;

class In3 extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'in3'
        );
    }

    /**
     * @throws ApiNotSupportedException
     * @return mixed[]|void
     */
    public function buildBodyPaymentsAPI()
    {
        throw new ApiNotSupportedException('In3 does not support the Payments API!');
    }
}
