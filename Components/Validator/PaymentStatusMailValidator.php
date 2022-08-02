<?php

namespace MollieShopware\Components\Validator;

use MollieShopware\Components\ConfigInterface;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\MollieShopware;
use Shopware\Models\Order\Order;

class PaymentStatusMailValidator
{

    /**
     * @var ConfigInterface
     */
    private $config;


    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @param Order $order
     * @param string $mollieStatus
     * @return bool
     */
    public function shouldSendPaymentStatusMail(Order $order, $mollieStatus)
    {
        # if our order uses klarna, then verify if its transition is "paid".
        # This means that it's paid for the merchant and in Mollie, but it does NOT mean
        # that the customer paid the amount to Klarna.
        # In this case we always skip payment status emails, because it would confuse the customer
        if ($order->getPayment()->getName() === MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_LATER) {
            if ($mollieStatus === PaymentStatus::MOLLIE_PAYMENT_PAID) {
                return false;
            }

            if ($mollieStatus === PaymentStatus::MOLLIE_PAYMENT_COMPLETED) {
                return false;
            }
        }

        return $this->config->isPaymentStatusMailEnabled();
    }
}
