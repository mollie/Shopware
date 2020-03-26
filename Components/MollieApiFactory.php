<?php

namespace MollieShopware\Components;

require_once __DIR__ . '/../Client/vendor/autoload.php';

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
        $this->requireDependencies();

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
                    'MollieShopware/1.5.9'
                );
            }
            catch (\Exception $ex) {
                //
            }
        }

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
