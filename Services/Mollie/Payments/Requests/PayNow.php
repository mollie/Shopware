<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;


use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Exceptions\ApiNotSupportedException;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;


class PayNow extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'klarnapaynow'
        );
    }

    /**
     * @return mixed[]|void
     * @throws ApiNotSupportedException
     */
    public function buildBodyPaymentsAPI()
    {
        throw new ApiNotSupportedException('Klarna Pay Now does not support the Payments API!');
    }

}
