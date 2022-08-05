<?php

namespace MollieShopware\Services\Mollie\Payments\Extractor;

use Mollie\Api\Resources\Payment;
use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;

class PaymentFailedDetailExtractor
{
    /**
     * @param Payment $payment
     * @return null|PaymentFailedDetails
     */
    public function extractDetails(Payment $payment)
    {
        if ($payment->details === null) {
            return null;
        }
        $details = $payment->details;
        if (!property_exists($details, 'failureReason')) {
            return null;
        }
        if (!property_exists($details, 'failureMessage')) {
            return null;
        }
        return new PaymentFailedDetails($details->failureReason, $details->failureMessage);
    }
}
