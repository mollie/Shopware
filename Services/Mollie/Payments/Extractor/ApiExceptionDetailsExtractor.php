<?php

namespace MollieShopware\Services\Mollie\Payments\Extractor;

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;

class ApiExceptionDetailsExtractor
{
    /**
     * @param ApiException $exception
     * @return null|PaymentFailedDetails
     */
    public function extractDetails(ApiException $exception)
    {
        if ($exception->getField() === null) {
            return null;
        }
        $field = $this->prepareField('error.' . $exception->getField());

        $messageDetails = $this->parseMessage($exception);
        if ($messageDetails === null) {
            return null;
        }

        return new PaymentFailedDetails($field, $messageDetails);
    }

    /**
     * @param string $field
     * @return string
     */
    private function prepareField($field)
    {
        $fieldParts = explode('.', $field);
        array_walk($fieldParts, function (&$fieldPart) {
            $fieldPart = ucfirst($fieldPart);
        });
        return implode('', $fieldParts);
    }

    /**
     * @param ApiException $exception
     * @return null|string
     */
    private function parseMessage(ApiException $exception)
    {
        $documentationSuffix = '';
        if (stripos($exception->getMessage(), 'Documentation:') !== false) {
            $documentationSuffix = '. Documentation:.*';
        }
        $fieldSuffix = '';
        if (stripos($exception->getMessage(), 'Field:') !== false) {
            $fieldSuffix = '. Field:.*';
        }
        $regEx = sprintf('/.*Error executing API call \((?<status>.*):(?<title>.*)\): (?<details>.*)%s%s/mi', $documentationSuffix, $fieldSuffix);

        if (preg_match($regEx, $exception->getMessage(), $matches)) {
            return $matches['details'];
        }
        return null;
    }
}
