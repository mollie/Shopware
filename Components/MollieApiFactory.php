<?php

namespace MollieShopware\Components;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\HttpAdapter\CurlMollieHttpAdapter;
use Mollie\Api\MollieApiClient;
use MollieShopware\MollieShopware;
use MollieShopware\Services\Mollie\Client\MollieHttpClient;
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
     * @throws ApiException
     * @return MollieApiClient
     */
    public function create($shopId = null)
    {
        // set the configuration for the shop
        $this->config->setShop($shopId);

        # either use the test or the live api key
        # depending on our sub shop configuration
        $apiKey = ($this->config->isTestmodeActive()) ? $this->config->getTestApiKey() : $this->config->getLiveApiKey();

        return $this->buildApiClient(
            $apiKey
        );
    }

    /**
     * @param null|int $shopId
     * @throws ApiException
     * @return MollieApiClient
     */
    public function createLiveClient($shopId = null)
    {
        // set the configuration for the shop
        $this->config->setShop($shopId);

        return $this->buildApiClient(
            $this->config->getLiveApiKey()
        );
    }

    /**
     * @param null|int $shopId
     * @throws ApiException
     * @return MollieApiClient
     */
    public function createTestClient($shopId = null)
    {
        // set the configuration for the shop
        $this->config->setShop($shopId);

        return $this->buildApiClient(
            $this->config->getTestApiKey()
        );
    }


    /**
     * @param $apiKey
     * @throws ApiException
     * @return MollieApiClient
     */
    private function buildApiClient($apiKey)
    {
        # in some rare peaks, the Mollie API might take a bit more time.
        # so we set it a higher connect timeout, and also a high enough response timeout
        $connectTimeout = 5;
        $responseTimeout = 10;
        $httpClient = new MollieHttpClient($connectTimeout, $responseTimeout);

        $client = new MollieApiClient($httpClient);

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
        } catch (\Exception $ex) {
            $this->logger->error(
                'Fatal error with Mollie API Key. Invalid Key: ' . $apiKey,
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        return $client;
    }
}
