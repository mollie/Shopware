<?php

namespace MollieShopware\Tests\Utils\Fixtures;

use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;


class PaymentAddressFixture
{

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
            'Munich',
            'DE'
        );
    }



}
