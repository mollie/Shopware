<?php

namespace MollieShopware\Exceptions;

use MollieShopware\Services\Mollie\Payments\Models\PaymentFailedDetails;

class MolliePaymentFailedException extends \Exception
{
    /**
     * @var ?PaymentFailedDetails
     */
    private $failedDetails = null;
    /**
     * MolliePaymentFailedException constructor.
     * @param string $transactionID
     * @param string $message
     */
    public function __construct($transactionID, $message)
    {
        parent::__construct('Payment failed for transaction: ' . $transactionID . ', ' . $message);
    }

    /**
     * @return bool
     */
    public function hasDetails()
    {
        return $this->failedDetails !== null;
    }

    /**
     * @return ?PaymentFailedDetails
     */
    public function getFailedDetails()
    {
        return $this->failedDetails;
    }

    /**
     * @param PaymentFailedDetails $failedDetails
     */
    public function setFailedDetails(PaymentFailedDetails $failedDetails): void
    {
        $this->failedDetails = $failedDetails;
    }

}
