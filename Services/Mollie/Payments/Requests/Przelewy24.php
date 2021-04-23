<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;


class Przelewy24 extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'przelewy24'
        );
    }

    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI()
    {
        $data = parent::buildBodyPaymentsAPI();

        # we pre-fill the email
        $data['billingEmail'] = $this->billingAddress->getEmail();

        return $data;
    }

}
