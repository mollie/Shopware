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

    /**
     * this test makes sure that nothing will be extracted if the field is not set in the exception
     * @return void
     */
    public function testFieldNotSet(): void
    {
        $exception = new ApiException();
        $result = $this->extractor->extractDetails($exception);
        $this->assertNull($result);
    }

    /**
     * this test makes sure that nothing is extracted is the message does not have a defined structure
     * @throws ApiException
     * @return void
     */
    public function testMessageHasInvalidFormat(): void
    {
        $exception = new ApiException('test', 1, 'test.field');

        $result = $this->extractor->extractDetails($exception);
        $this->assertNull($result);
    }

    /**
     * this test makes sure that the message detailed are extrated from a full exception message
     * also the field gets a prefix "Error". the reason code is used for the template variable to override the original message
     * @throws ApiException
     * @return void
     */
    public function testExtractMessageAndFieldFromException(): void
    {
        $exception = new ApiException('Error executing API call (123:Test): Test Message', 123, 'test.field');

        $expectedResult = new PaymentFailedDetails('ErrorTestField', 'Test Message');
        $actualResult = $this->extractor->extractDetails($exception);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * this test makes sure that the documentation part of the message is ignored
     * @throws ApiException
     * @return void
     */
    public function testMessageWithDocumentation(): void
    {
        $exception = new ApiException('Error executing API call (123:Test): Test Message. Documentation: https://mollie.com', 123, 'test.field');

        $expectedResult = new PaymentFailedDetails('ErrorTestField', 'Test Message');
        $actualResult = $this->extractor->extractDetails($exception);
        $this->assertEquals($expectedResult, $actualResult);
    }
}
