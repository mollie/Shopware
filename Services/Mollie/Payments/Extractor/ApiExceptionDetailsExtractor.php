<?php

namespace MollieShopware\Services\Mollie\Payments\Extractor;

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;

class ApiExceptionDetailsExtractor
{
    /**
     * @param ApiException $exception
     * @return PaymentFailedDetails|null
     */
    public function extractDetails(ApiException $exception)
    {
        if ($exception->getField() === null) {
            return null;
        }

        $messageDetails = $this->parseMessage($exception);
        if ($messageDetails === null) {
            return null;
        }
        return new PaymentFailedDetails($exception->getField(), $messageDetails);

    }

    /**
     * @param ApiException $message
     * @return string|null
     */
    private function parseMessage(ApiException $exception)
    {
        $documentationSuffix = '';
        if ($exception->getDocumentationUrl() !== null) {
            $documentationSuffix = '. Documentation:';
        }
        $regEx = sprintf('/Error executing API call \((?<status>.*):(?<title>.*)\): (?<details>.*)%s/mi', $documentationSuffix);

        if (preg_match($regEx, $exception->getMessage(), $matches, PREG_OFFSET_CAPTURE, 0)) {
            return $matches['details'];
        }
        return null;
    }
}