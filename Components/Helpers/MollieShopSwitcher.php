<?php

namespace MollieShopware\Components\Helpers;

use MollieShopware\Components\Config;
use MollieShopware\Components\MollieApiFactory;
use Psr\Container\ContainerInterface;

class MollieShopSwitcher
{

    /**
     * @var ContainerInterface
     */
    private $container;


    /**
     * MollieShopSwitcher constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    
    /**
     * @param $shopId
     * @return Config
     */
    public function getConfig($shopId)
    {
        /** @var Config $config */
        $config = $this->container->get('mollie_shopware.config');

        $config->setShop($shopId);

        return $config;
    }

    /**
     * @param $shopId
     * @return \Mollie\Api\MollieApiClient
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function getMollieApi($shopId)
    {
        /** @var MollieApiFactory $apiFactory */
        $apiFactory = Shopware()->Container()->get('mollie_shopware.api_factory');

        return $apiFactory->create($shopId);
    }

}
