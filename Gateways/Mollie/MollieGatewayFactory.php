<?php

namespace MollieShopware\Gateways\Mollie;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\MollieApiFactory;
use Psr\Container\ContainerInterface;

class MollieGatewayFactory
{

    /**
     * @var ContainerInterface
     */
    private $container;


    /**
     * @param $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }


    /**
     * @param MollieApiClient $client
     * @return MollieGateway
     */
    public function create(MollieApiClient $client)
    {
        return new MollieGateway($client);
    }

    /**
     * @param $shopId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return MollieGateway
     */
    public function createForShop($shopId)
    {
        /** @var MollieApiFactory $apiFactory */
        $apiFactory = $this->container->get('mollie_shopware.api_factory');

        $apiClient = $apiFactory->create($shopId);

        return $this->create($apiClient);
    }
}
