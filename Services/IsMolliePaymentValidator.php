<?php

namespace MollieShopware\Services;

use Shopware\Models\Payment\Payment;
use Shopware\Models\Plugin\Plugin;

class IsMolliePaymentValidator
{
    /** @var string */
    private $pluginName;

    /**
     * @param string $pluginName
     */
    public function __construct($pluginName)
    {
        $this->pluginName = $pluginName;
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    public function validate(Payment $payment)
    {
        /** @var null|Plugin $plugin */
        $plugin = $payment->getPlugin();

        if (!($plugin instanceof Plugin)) {
            return false;
        }

        return $plugin->getName() === $this->pluginName;
    }
}
