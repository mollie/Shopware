<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Models;

use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails
 */
class PaymentFailedDetailsTest extends TestCase
{
    public function testGetters()
    {
        $failedDetails = new PaymentFailedDetails('code', 'message');
        $this->assertSame('code', $failedDetails->getReasonCode());
        $this->assertSame('message', $failedDetails->getReasonMessage());
    }
}
