<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Extractor;

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Services\Mollie\Payments\Extractor\ApiExceptionDetailsExtractor;
use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MollieShopware\Services\Mollie\Payments\Extractor\ApiExceptionDetailsExtractor
 */
class ApiExceptionDetailsExtractorTest extends TestCase
{
    /**
     * @var ApiExceptionDetailsExtractor
     */
    private $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ApiExceptionDetailsExtractor();
    }

    public function testFieldNotSet(): void
    {
        $exception = new ApiException();
        $result = $this->extractor->extractDetails($exception);
        $this->assertNull($result);
    }

    public function testMessageHasInvalidFormat(): void
    {
        $exception = new ApiException('test', 1, 'test.field');

        $result = $this->extractor->extractDetails($exception);
        $this->assertNull($result);
    }

    public function testMessageWithoutDocumentation(): void
    {
        $exception = new ApiException('Error executing API call (123:Test): Test Message', 123, 'test.field');

        $expectedResult = new PaymentFailedDetails('ErrorTestField', 'Test Message');
        $actualResult = $this->extractor->extractDetails($exception);
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testMessageWithDocumentation(): void
    {
        $exception = new ApiException('Error executing API call (123:Test): Test Message. Documentation: https://mollie.com', 123, 'test.field');

        $expectedResult = new PaymentFailedDetails('ErrorTestField', 'Test Message');
        $actualResult = $this->extractor->extractDetails($exception);
        $this->assertEquals($expectedResult, $actualResult);
    }
}
