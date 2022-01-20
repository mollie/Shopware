<?php

namespace MollieShopware\Tests\Utils\Fakes\Config;

use MollieShopware\Components\ConfigInterface;


class FakeConfig implements ConfigInterface
{

    /**
     * @var bool
     */
    private $paymentStatusMail;

    /**
     * @var bool
     */
    private $useMolliePaymentMethodLimits;

    /**
     * @param bool $paymentStatusMail
     */
    public function __construct(bool $paymentStatusMail)
    {
        $this->paymentStatusMail = $paymentStatusMail;
    }

    /**
     * @return bool
     */
    public function isPaymentStatusMailEnabled()
    {
        return $this->paymentStatusMail;
    }

    /**
     * @param bool $useMolliePaymentMethodLimits
     * @return $this
     */
    public function setUseMolliePaymentMethodLimits($useMolliePaymentMethodLimits)
    {
        $this->useMolliePaymentMethodLimits = $useMolliePaymentMethodLimits;
        return $this;
    }

    /**
     * @return bool
     */
    public function useMolliePaymentMethodLimits()
    {
        return $this->useMolliePaymentMethodLimits;
    }
}
