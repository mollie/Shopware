<?php

	// Mollie Shopware Plugin Version: 1.3.10.1

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
