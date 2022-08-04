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
        $field = $this->prepareField($exception->getField());

        $messageDetails = $this->parseMessage($exception);
        if ($messageDetails === null) {
            return null;
        }

        return new PaymentFailedDetails($field, $messageDetails);

    }

    private function prepareField(string $field): string
    {
        $fieldParts = explode('.', $field);
        array_walk($fieldParts, function (&$fieldPart) {
            $fieldPart = ucfirst($fieldPart);
        });
        return implode('', $fieldParts);
    }

    /**
     * @param ApiException $message
     * @return string|null
     */
    private function parseMessage(ApiException $exception)
    {
        $documentationSuffix = '';
        if (stripos($exception->getMessage(), 'Documentation:') !== false) {
            $documentationSuffix = '. Documentation:';
        }
        $regEx = sprintf('/.*Error executing API call \((?<status>.*):(?<title>.*)\): (?<details>.*)%s/mi', $documentationSuffix);

        if (preg_match($regEx, $exception->getMessage(), $matches)) {
            return $matches['details'];
        }
        return null;
    }
}