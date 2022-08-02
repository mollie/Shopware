<?php

namespace MollieShopware\Tests\Components\Mollie\Services\TransactionUUID;

use MollieShopware\Components\Mollie\Services\TransactionUUID\TransactionUUID;
use MollieShopware\Tests\Utils\Fakes\Transaction\FakeUnixTimestampGenerator;
use PHPUnit\Framework\TestCase;

class TransactionUUIDTest extends TestCase
{

    /**
     * This test verifies that our transaction UUID is correctly built.
     * The UUID is used as description and order number when creating payments.
     * It's a unique number to match a transaction between Shopware and Mollie if
     * a real Shopware order does not YET exist. In this case this ID is used.
     */
    public function testUUID()
    {
        $uuid = new TransactionUUID(
            new FakeUnixTimestampGenerator('000000000000')
        );

        $description = $uuid->generate(2, 'ABCDEFGHIJKLMNOP');

        $this->assertEquals('000000000000' . '2' . 'MNOP', $description);
    }
}
