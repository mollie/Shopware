<?php

namespace MollieShopware\Components\CustomConfig;

class CustomConfig
{

    /**
     * main node for a custom mollie configuration
     */
    const KEY_MOLLIE_CONFIG = 'mollie';

    /**
     * @var array
     */
    private $config;


    /**
     * CustomConfig constructor.
     *
     * @param array $shopwareConfig
     */
    public function __construct($shopwareConfig)
    {
        # let's verify if we have a custom config for mollie
        # with that custom config its possible to use a different
        # options for developers that might not be possible in
        # the default plugin
        if (isset($shopwareConfig[self::KEY_MOLLIE_CONFIG])) {
            $this->config = $shopwareConfig[self::KEY_MOLLIE_CONFIG];
        } else {
            $this->config = [];
        }
    }

    /**
     * Returns a custom base URL of the shop,
     * if this has been set in the config.
     * It can be used to use a different shop url
     * for e.g. webhooks or other use cases
     *
     * @return string
     */
    public function getShopDomain()
    {
        if (isset($this->config['shop_domain'])) {
            return $this->config['shop_domain'];
        }

        return "";
    }
}
