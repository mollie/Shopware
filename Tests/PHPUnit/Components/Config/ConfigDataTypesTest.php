<?php

namespace MollieShopware\Tests\Components\Config;

use Doctrine\ORM\EntityManagerInterface;
use Mollie\Api\Endpoints\ProfileEndpoint;
use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Config;
use MollieShopware\Components\Config\ConfigDataTypes;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Services\ShopService;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Shopware\Models\Shop\Shop;

class ConfigDataTypesTest extends TestCase
{


    /**
     * @return array[]
     */
    public function getBoolDataSet()
    {
        return
            [
                [true, 'yes'],
                [true, 'ja'],
                [true, 'si'],
                [true, 'sí'],
                [true, 'oui'],
                # ----------------------------------
                [true, 'Yes'],
                [true, 'Ja'],
                [true, 'Si'],
                [true, 'Sí'],
                [true, 'Oui'],
                # ----------------------------------
                [false, 'No'],
                [false, 'Nein'],
                [false, 'Nee'],
                [false, 'No'],
                [false, 'Non'],
                # ----------------------------------
                [true, true],
                [false, 1],
                [false, "1"],
                # ----------------------------------
                [false, false],
                [false, null],
                [false, ''],
            ];
    }

    /**
     *
     * This test verifies that we always get the correct
     * bool value for our input.
     *
     * @dataProvider  getBoolDataSet
     *
     * @param $expected
     * @param $value
     * @return void
     */
    public function testGetConfigBool($expected, $value)
    {
        $dataTypes = new ConfigDataTypes();

        $actual = $dataTypes->getBoolValue($value);

        $this->assertEquals($expected, $actual);
    }
}
