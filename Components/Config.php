<?php

// Mollie Shopware Plugin Version: 1.4.8

namespace MollieShopware\Components;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class Config
{
    /** @var \Shopware\Components\Plugin\ConfigReader */
    protected $configReader;

    /** @var array */
    protected $data = null;

    public function __construct(\Shopware\Components\Plugin\ConfigReader $configReader)
    {
        $this->configReader = $configReader;
    }

    /**
     * Get the Shopware config for a Shopware shop
     *
     * @param string $key
     * @param string $default
     *
     * @return array
     */
    public function get($key = null, $default = null)
    {
        if (empty($this->data)) {
            try {
                $shop = Shopware()->Shop();
            }
            catch(ServiceNotFoundException $ex) {
                $shop = null;
            }

            // get config for shop or for main if shopid is null
            $parts = explode('\\', __NAMESPACE__);
            $name = array_shift($parts);
            $this->data = $this->configReader->getByPluginName($name, $shop);
        }

        if (!empty($key)) {
            return isset($this->data[$key]) ? $this->data[$key] : $default;
        }

        return $this->data;
    }

    /**
     * Get the API key
     *
     * @return string
     */
    public function apiKey()
    {
        return $this->get('api-key');
    }

    /**
     * Whether to send status mails to the customer when the status of the payment changes
     *
     * @return boolean
     */
    public function sendStatusMail()
    {
        return $this->get('send_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to send status mails to the customer when the payment has been refunded
     *
     * @return boolean
     */
    public function sendRefundStatusMail()
    {
        return $this->get('send_refund_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to automatically reset stock after a failed or canceled payment
     *
     * @return boolean
     */
    public function autoResetStock()
    {
        return $this->get('auto_reset_stock', 'no') == 'yes';
    }

    /**
     * @return string
     */
    public function extraMetaData()
    {
        return $this->get('extra_metadata', '<metadata><Customer></Customer></metadata>');
    }

    /**
     * @return bool
     */
    public function useOrdersApiOnlyWhereMandatory()
    {
        return ($this->get('orders_api_only_where_mandatory', 'yes') == 'yes');
    }

    /**
     * @return int
     */
    public function getAuthorizedPaymentStatusId()
    {
        $statusModel = new \Shopware\Models\Order\Status();
        $paymentStatus = null;
        $configuredStatus = $this->get('payment_authorized_status', 'ordered');

        // set default payment status, considering older Shopware versions that don't have the ordered status
        if (defined('\Shopware\Models\Order\Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED'))
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED;
        else
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED;

        // set different payment status if configured
        if ($configuredStatus == 'preliminarily_accepted')
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED;
        if ($configuredStatus == 'accepted')
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED;

        return $paymentStatus;
    }

    /**
     * @return bool
     */
    public function updateOrderStatus()
    {
        return ($this->get('orders_api_update_order_status', 'no') == 'yes');
    }

    /**
     * @return bool
     */
    public function cancelFailedOrders()
    {
        return ($this->get('auto_cancel_failed_orders', 'yes') == 'yes');
    }

    /**
     * @return int
     */
    public function getShippedStatus()
    {
        return (int) $this->get('klarna_shipped_status', -1);
    }
}
