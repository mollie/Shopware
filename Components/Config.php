<?php

	// Mollie Shopware Plugin Version: 1.3.7

namespace MollieShopware\Components;

use Shopware\Components\Plugin\ConfigReader;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class Config
{
    /**
     * @var Shopware\Components\Plugin\ConfigReader
     */
    protected $configReader;

    /**
     * @var array
     */
    protected $data = null;

    public function __construct(ConfigReader $configReader)
    {
        $this->configReader = $configReader;
    }

    /**
     * Get the Shopware config for a Shopware shop
     *
     * @param  int $shopId
     * @return array
     */
    public function get($key = null, $default = null)
    {
        if (is_null($this->data)) {

            try{
                $shop = Shopware()->Shop();
            }
            catch(ServiceNotFoundException $e){
                $shop = null;
            }

            // get config for shop or for main if shopid is null
            $parts = explode('\\', __NAMESPACE__);
            $name = array_shift($parts);
            $this->data = $this->configReader->getByPluginName($name, $shop);
        }

        if (!is_null($key)) {
            return isset($this->data[$key]) ? $this->data[$key] : $default;
        }

        return $this->data;
    }

    /**
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
}
