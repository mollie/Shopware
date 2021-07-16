<?php


namespace MollieShopware\Components\Config;

use MollieShopware\Components\Config;
use Psr\Container\ContainerInterface;


class ConfigFactory
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
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @param int $shopId
     * @return Config
     */
    public function getForShop($shopId)
    {
        /** @var Config $config */
        $config = $this->container->get('mollie_shopware.config');

        $config->setShop($shopId);

        return $config;
    }

}
