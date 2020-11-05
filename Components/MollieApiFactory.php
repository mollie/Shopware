<?php

namespace MollieShopware\Components;

require_once __DIR__ . '/../Client/vendor/autoload.php';

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use MollieShopware\MollieShopware;
use Psr\Log\LoggerInterface;

class MollieApiFactory
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }


    /**
     * @param null $shopId
     * @return MollieApiClient
     * @throws ApiException
     */
    public function create($shopId = null)
    {
        $this->requireDependencies();

        // set the configuration for the shop
        $this->config->setShop($shopId);

        # either use the test or the live api key
        # depending on our sub shop configuration
        $apiKey = ($this->config->isTestmodeActive()) ? $this->config->getTestApiKey() : $this->config->apiKey();

        return $this->buildApiClient(
            $apiKey
        );
    }

    /**
     * @param null $shopId
     * @return MollieApiClient
     * @throws ApiException
     */
    public function createLiveClient($shopId = null)
    {
        $this->requireDependencies();

        // set the configuration for the shop
        $this->config->setShop($shopId);

        return $this->buildApiClient(
            $this->config->apiKey()
        );
    }

    /**
     * @param null $shopId
     * @return MollieApiClient
     * @throws ApiException
     */
    public function createTestClient($shopId = null)
    {
        $this->requireDependencies();

        // set the configuration for the shop
        $this->config->setShop($shopId);

        return $this->buildApiClient(
            $this->config->getTestApiKey()
        );
    }


    /**
     * @param $apiKey
     * @return MollieApiClient
     * @throws ApiException
     */
    private function buildApiClient($apiKey)
    {
        $client = new MollieApiClient();

        $shopwareVersion = Shopware()->Config()->get('Version');

        # this parameter has been deprecated
        # we need a new version access for shopware 5.5 and up.
        # deprecated to be removed in 5.6
        if ($shopwareVersion === '___VERSION___') {
            /** @var \Shopware\Components\ShopwareReleaseStruct $release */
            $release = Shopware()->Container()->get('shopware.release');
            $shopwareVersion = $release->getVersion();
        }

        // add platform name and version
        $client->addVersionString('Shopware/' . $shopwareVersion);

        // add plugin name and version
        $client->addVersionString(
            'MollieShopware/' . MollieShopware::PLUGIN_VERSION
        );

        try {

            // set the api key based on the configuration
            $client->setApiKey($apiKey);

        } catch (\Throwable $ex) {
            $this->logger->error(
                'Fatal error with Mollie API Key. Invalid Key: ' . $apiKey,
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $client;
    }

    /**
     *
     */
    private function requireDependencies()
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
