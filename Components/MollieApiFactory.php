<?php

namespace MollieShopware\Components;

require_once __DIR__ . '/../Client/vendor/autoload.php';

use Mollie\Api\Exceptions\ApiException;
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
     * @param null $shopId
     *
     * @return MollieApiClient
     *
     * @throws ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function create($shopId = null)
    {
        $this->requireDependencies();

        if (empty($this->apiClient)) {
            $this->apiClient = new MollieApiClient();

            try {
                // add platform name and version
                $this->apiClient->addVersionString(
                    'Shopware/' .
                    Shopware()->Container()->getParameter('shopware.release.version')
                );

                // add plugin name and version
                $this->apiClient->addVersionString(
                    'MollieShopware/1.5.20'
                );
            }
            catch (\Exception $ex) {
                //
            }
        }

        // set the configuration for the shop
        $this->config->setShop($shopId);

        // set the api key based on the configuration
        $this->apiClient->setApiKey($this->config->apiKey());

        return $this->apiClient;
    }

    public function requireDependencies()
    {
        // Load composer libraries
        if (file_exists(__DIR__ . '/../Client/vendor/scoper-autoload.php')) {
            require_once __DIR__ . '/../Client/vendor/scoper-autoload.php';
        }

        // Load guzzle functions
        if (file_exists(__DIR__ . '/../Client/vendor/guzzlehttp/guzzle/src/functions_include.php')) {
            require_once __DIR__ . '/../Client/vendor/guzzlehttp/guzzle/src/functions_include.php';
        }

        // Load promises functions
        if (file_exists(__DIR__ . '/../Client/vendor/guzzlehttp/promises/src/functions_include.php')) {
            require_once __DIR__ . '/../Client/vendor/guzzlehttp/promises/src/functions_include.php';
        }

        // Load psr7 functions
        if (file_exists(__DIR__ . '/../Client/vendor/guzzlehttp/psr7/src/functions_include.php')) {
            require_once __DIR__ . '/../Client/vendor/guzzlehttp/psr7/src/functions_include.php';
        }

        // Load client
        if (file_exists(__DIR__ . '/../Client/vendor/mollie/mollie-api-php/src/MollieApiClient.php')) {
            require_once __DIR__ . '/../Client/vendor/mollie/mollie-api-php/src/MollieApiClient.php';
        }
    }
}
