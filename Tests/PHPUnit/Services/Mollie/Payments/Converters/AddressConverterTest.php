<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Builders;

use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use PHPUnit\Framework\TestCase;

class AddressConverterTest extends TestCase
{


    /**
     * This test verifies that the building of our payment address
     * works correctly.
     */
    public function testAddress()
    {
        $builder = new AddressConverter();

        $address = new PaymentAddress(
            'mr',
            'The',
            'Mollie',
            'dev@mollie.local',
            'Mollie Street',
            '-',
            '12345',
            'Mollie Town',
            'NL'
        );

        $data = $builder->convertAddress($address);

        $expected = [
            'title' => 'mr',
            'givenName' => 'The',
            'familyName' => 'Mollie',
            'email' => 'dev@mollie.local',
            'streetAndNumber' => 'Mollie Street',
            'streetAdditional' => '-',
            'postalCode' => '12345',
            'city' => 'Mollie Town',
            'country' => 'NL'
        ];

        $this->assertSame($expected, $data);
    }
}
