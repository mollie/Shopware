<?php

namespace MollieShopware\Tests\Services\TokenAMollieOrderRequestAnonymizernonymizer;

use MollieShopware\Services\MollieOrderRequestAnonymizer\MollieOrderRequestAnonymizer;
use PHPUnit\Framework\TestCase;

class MollieOrderRequestAnonymizerTest extends TestCase
{

    /**
     * This test verifies that we get an empty string
     * if we have an invalid value that is just NULL.
     */
    public function testAnonymizeNull()
    {
        $anonymizer = new MollieOrderRequestAnonymizer('****');

        $requestData = [];

        $anonymized = $anonymizer->anonymize($requestData);

        $this->assertEquals([], $anonymized);
    }

    /**
     * This test verifies that we do not get any errors
     * if the keys for anonymiziation do not exist.
     */
    public function testMissingKeys()
    {
        $anonymizer = new MollieOrderRequestAnonymizer('****');

        $requestData = [
            'zipcode' => 'ABC',
        ];

        $expected = [
            'zipcode' => 'ABC',
        ];

        $anonymized = $anonymizer->anonymize($requestData);

        $this->assertEquals($expected, $anonymized);
    }

    /**
     * This test verifies that all our required confidental
     * data is correctly anonymized.
     */
    public function testFullData()
    {
        $anonymizer = new MollieOrderRequestAnonymizer('****');

        $requestData = [
            'billingAddress' => [
                'organizationName' => 'a',
                'streetAndNumber' => 'b',
                'givenName' => 'c',
                'familyName' => 'd',
                'email' => 'e',
                'phone' => 'r',
            ],
            'shippingAddress' => [
                'organizationName' => 'a',
                'streetAndNumber' => 'b',
                'givenName' => 'c',
                'familyName' => 'd',
                'email' => 'e',
                'phone' => 'r',
            ],
        ];

        $expected = [
            'billingAddress' => [
                'organizationName' => '****',
                'streetAndNumber' => '****',
                'givenName' => '****',
                'familyName' => '****',
                'email' => '****',
                'phone' => '****',
            ],
            'shippingAddress' => [
                'organizationName' => '****',
                'streetAndNumber' => '****',
                'givenName' => '****',
                'familyName' => '****',
                'email' => '****',
                'phone' => '****',
            ],
        ];

        $anonymized = $anonymizer->anonymize($requestData);

        $this->assertEquals($expected, $anonymized);
    }
}
