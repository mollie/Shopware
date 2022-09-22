<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Extractor;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use MollieShopware\Services\Mollie\Payments\Extractor\PaymentFailedDetailExtractor;
use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MollieShopware\Services\Mollie\Payments\Extractor\PaymentFailedDetailExtractor
 */
class PaymentFailedDetailExtractorTest extends TestCase
{
    /**
     * @var PaymentFailedDetailExtractor
     */
    private $extractor;

    public function setUp(): void
    {
        $this->extractor = new PaymentFailedDetailExtractor();
    }

    public function testDetailsNotSet(): void
    {
        $payment = new Payment($this->createMock(MollieApiClient::class));
        $details = $this->extractor->extractDetails($payment);
        $this->assertNull($details);
    }

    public function testFailureReasonNotSet(): void
    {
        $payment = new Payment($this->createMock(MollieApiClient::class));
        $payment->details = new \stdClass();
        $details = $this->extractor->extractDetails($payment);
        $this->assertNull($details);
    }

    public function testFailureMessageNotSet(): void
    {
        $payment = new Payment($this->createMock(MollieApiClient::class));
        $paymentDetails = new \stdClass();
        $paymentDetails->failureReason = 'test';
        $payment->details = $paymentDetails;
        $details = $this->extractor->extractDetails($payment);
        $this->assertNull($details);
    }

    public function testDetailsExtracted(): void
    {
        $payment = new Payment($this->createMock(MollieApiClient::class));
        $paymentDetails = new \stdClass();
        $paymentDetails->failureReason = 'test';
        $paymentDetails->failureMessage = 'test message';
        $payment->details = $paymentDetails;

        $expectedDetails = new PaymentFailedDetails('test', 'test message');
        $actualDetails = $this->extractor->extractDetails($payment);
        $this->assertEquals($expectedDetails, $actualDetails);
    }
}
