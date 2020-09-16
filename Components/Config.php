<?php

namespace MollieShopware\Components;

use Exception;
use MollieShopware\Components\Services\ShopService;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Models\Order\Status;
use Shopware\Models\Shop\Repository;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class Config
{
    const TRANSACTION_NUMBER_TYPE_MOLLIE = 'mollie';
    const TRANSACTION_NUMBER_TYPE_PAYMENT_METHOD = 'payment_method';
    const INHERITED_CONFIG_VALUE = 'inherited';

    /** @var ConfigReader */
    private $configReader;

    /** @var int */
    private $shopId = null;

    /** @var array */
    private $data = null;

    /** @var ShopService */
    private $shopService;

    public function __construct(
        ConfigReader $configReader,
        ShopService $shopService
    )
    {
        $this->configReader = $configReader;
        $this->shopService = $shopService;
    }

    /**
     * Get the Shopware config for a Shopware shop
     *
     * @param string $key
     * @param string $default
     *
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        if (empty($this->data)) {
            $shop = null;

            // if a shop id is given, get the shop object for the given id
            if ($this->shopId !== null) {
                try {
                    $shop = $this->shopService->shopById($this->shopId);
                } catch (ServiceNotFoundException $ex) {
                    $shop = null;
                }
            }

            // if no shop was given, or the given shop was not found, fallback to the current shop
            if ($shop === null) {
                try {
                    $shop = Shopware()->Shop();
                } catch (Exception $e) {
                    //
                }
            }

            // get config for shop or for main if shopid is null
            $parts = explode('\\', __NAMESPACE__);
            $name = array_shift($parts);
            $config = $this->configReader->getByPluginName($name, $shop);
            $config = $this->addInheritedConfig($config, $name);
            $this->data = $config;
        }

        if (!empty($key)) {
            return isset($this->data[$key]) ? $this->data[$key] : $default;
        }

        return $this->data;
    }

    /**
     * @param array $config
     * @param string $name
     * @return array
     */
    private function addInheritedConfig(array $config, $name)
    {
        // check if inherited or null fields are in config to get from default shop
        if (!in_array(self::INHERITED_CONFIG_VALUE, $config) && !in_array(null, $config)) {
            return $config;
        }

        // get default shop
        /** @var Repository $shopRepository */
        $shopRepository = Shopware()->Models()->getRepository('Shopware\\Models\\Shop\\Shop');
        $mainShop = $shopRepository->getActiveDefault();

        if ($mainShop === null) {
            return $config;
        }

        $inheritedConfig = $this->configReader->getByPluginName($name, $mainShop);

        // replace inherited or null fields in config
        foreach ($config as $key => $value) {
            if ($value === self::INHERITED_CONFIG_VALUE || $value === null) {
                $config[$key] = $inheritedConfig[$key];
            }
        }

        return $config;
    }

    /**
     * Sets the current shop.
     *
     * @param $shopId
     */
    public function setShop($shopId)
    {
        // Set the shop ID
        $this->shopId = $shopId;

        // Reset the data
        $this->data = null;
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
     * @return string
     */
    public function getTransactionNumberType()
    {
        return (string) $this->get('transaction_number_type', self::TRANSACTION_NUMBER_TYPE_MOLLIE);
    }

    /**
     * @return int
     */
    public function getAuthorizedPaymentStatusId()
    {
        $statusModel = new Status();
        $paymentStatus = null;
        $configuredStatus = $this->get('payment_authorized_status', 'ordered');

        // set default payment status, considering older Shopware versions that don't have the ordered status
        if (defined('\Shopware\Models\Order\Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED'))
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED;
        else
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED;

        // set different payment status if configured
        if ($configuredStatus === 'preliminarily_accepted')
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED;
        if ($configuredStatus === 'accepted')
            $paymentStatus = $statusModel::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED;

        return $paymentStatus;
    }

    /**
     * @return bool
     */
    public function updateOrderStatus()
    {
        return ($this->get('orders_api_update_order_status', 'no') === 'yes');
    }

    /**
     * @return bool
     */
    public function cancelFailedOrders()
    {
        return ($this->get('auto_cancel_failed_orders', 'yes') === 'yes');
    }

    /**
     * @return int
     */
    public function getKlarnaShipOnStatus()
    {
        return (int) $this->get('klarna_ship_on_status', Status::ORDER_STATE_COMPLETELY_DELIVERED);
    }

    /**
     * @return int
     */
    public function getShippedStatus()
    {
        return (int) $this->get('klarna_shipped_status', -1);
    }

    /**
     * @return string
     */
    public function resetInvoiceAndShipping()
    {
        return ($this->get('reset_invoice_shipping', 'no') === 'yes');
    }

    /**
     * @return bool
     */
    public function createOrderBeforePayment()
    {
        return ($this->get('create_order_before_payment', 'yes') === 'yes');
    }

    /**
     * @return bool
     */
    public function enableCreditCardComponent()
    {
        return (bool) $this->get('enable_credit_card_component', true);
    }

    /**
     * @return bool
     */
    public function enableCreditCardComponentStyling()
    {
        return (bool) $this->get('enable_credit_card_component_styling', true);
    }

    /**
     * @return int|null
     */
    public function mollieShopwareUserId()
    {
        $userId = $this->get('mollie_shopware_user_id', null);

        if ((string) $userId === '') {
            return null;
        }

        return (int) $userId;
    }
}
