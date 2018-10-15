<?php

<<<<<<< HEAD
	// Mollie Shopware Plugin Version: 1.3.1
=======
	// Mollie Shopware Plugin Version: 1.3.2
>>>>>>> order_position_fix

namespace MollieShopware\Components;

use Mollie\Api\MollieApiClient;

class MollieApiFactory
{
    /**
     * @var \MollieShopware\Components\Config
     */
    protected $config;

    /**
     * @var MollieApiClient
     */
    protected $mollieApi;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create()
    {
        if (empty($this->mollieApi)) {
            $apiKey = $this->config->apikey();

            $this->mollieApi = new MollieApiClient;
            $this->mollieApi->setApiKey($apiKey);
        }

        return $this->mollieApi;
    }
}
