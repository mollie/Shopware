<?php

namespace MollieShopware\Components;

use Mollie\Api\MollieApiClient;

class MollieApiFactory
{
    /** @var \MollieShopware\Components\Config */
    protected $config;

    /** @var MollieApiClient */
    protected $apiClient;

    /**
     * MollieApiFactory constructor
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Create the API client
     *
     * @return MollieApiClient
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function create()
    {
        if (empty($this->apiClient)) {
            $this->apiClient = new MollieApiClient();
            $this->apiClient->setApiKey($this->config->apikey());

            try {
                // add platform name and version
                $this->apiClient->addVersionString(
                    'Shopware/' .
                    Shopware()->Container()->getParameter('shopware.release.version')
                );

                // add plugin name and version
                $this->apiClient->addVersionString(
                    'MollieShopware/1.4.5'
                );
            }
            catch (\Exception $ex) {
                //
            }
        }

        return $this->apiClient;
    }
}
