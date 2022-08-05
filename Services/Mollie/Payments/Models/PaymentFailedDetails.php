<?php

namespace MollieShopware\Services\Mollie\Payments\Models;

class PaymentFailedDetails
{
    /**
     * @var string
     */
    private $reasonCode;
    /**
     * @var string
     */
    private $reasonMessage;

    /**
     * @param string $reasonCode
     * @param string $reasonMessage
     */
    public function __construct($reasonCode, $reasonMessage)
    {
        $this->reasonCode = $reasonCode;
        $this->reasonMessage = $reasonMessage;
    }

    /**
     * @return string
     */
    public function getReasonCode()
    {
        return $this->reasonCode;
    }

    /**
     * @return string
     */
    public function getReasonMessage()
    {
        return $this->reasonMessage;
    }
}
