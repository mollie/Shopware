<?php

namespace MollieShopware\Tests\Components\Logger\Services;

use MollieShopware\Components\Logger\Services\IPAnonymizer;
use PHPUnit\Framework\TestCase;

class IPAnonymizerTest extends TestCase
{

    /**
     * This test verifies that our placeholder is used
     * correctly when anonymizing our ip address.
     *
     * @covers \MollieShopware\Components\Logger\Services\IPAnonymizer
     * @covers \MollieShopware\Components\Logger\Services\IPAnonymizer::anonymize
     * @covers \MollieShopware\Components\Logger\Services\IPAnonymizer::isValidIP
     */
    public function testPlaceholder()
    {
        $service = new IPAnonymizer('*');
        $anonymousIP = $service->anonymize('192.1.1.5');

        $this->assertEquals('192.1.1.*', $anonymousIP);
    }

    /**
     * This function verifies that the
     * anonymization of the IP addresses work properly
     * by setting the last digit to 0.
     *
     * @dataProvider getIPData
     *
     * @covers \MollieShopware\Components\Logger\Services\IPAnonymizer
     * @covers \MollieShopware\Components\Logger\Services\IPAnonymizer::anonymize
     * @covers \MollieShopware\Components\Logger\Services\IPAnonymizer::isValidIP
     *
     * @param string $expected
     * @param string $ip
     */
    public function testAnonymizing($expected, $ip)
    {
        $service = new IPAnonymizer('0');
        $anonymousIP = $service->anonymize($ip);

        $this->assertEquals($expected, $anonymousIP);
    }

    /**
     * @return array
     */
    public function getIPData()
    {
        return [
            'yes-correct-ip' => ['192.168.6.0', '192.168.6.255'],
            'yes-ip-with-spaces-1' => ['192.168.6.0', '192.168.6.255 '],
            'yes-ip-with-spaces-2' => ['192.168.6.0', ' 192.168.6.255'],
            'no-invalid-with-spaces' => ['', ' 192.168 .6.255'],
            'no-invalid-no-ip-1' => ['', '120 '],
            'no-invalid-no-ip-2' => ['', 'test'],
        ];
    }
}
