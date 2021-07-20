<?php

namespace MollieShopware\Tests\Utils\Fixtures;

use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;


class PaymentAddressFixture
{

    /**
     * @var string
     */
    private $city;


    /**
     * PaymentAddressFixture constructor.
     * @param string $city
     */
    public function __construct($city)
    {
        $this->city = $city;
    }


    /**
     * @return PaymentAddress
     */
    public function buildAddress()
    {
        return new PaymentAddress(
            'mr',
            'Max',
            'Mustermann',
            'dev@mollie.local',
            'Mollie Street',
            'Addon',
            '1000',
            $this->city,
            'DE'
        );
    }

}
