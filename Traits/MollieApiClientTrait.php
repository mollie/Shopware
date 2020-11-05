<?php

namespace MollieShopware\Traits;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Logger;
use MollieShopware\Components\MollieApiFactory;
use Psr\Log\LoggerInterface;

trait MollieApiClientTrait
{

    /**
     * @param null $shopId
     * @return MollieApiClient|null
     * @throws \Exception
     */
    private function getMollieApi($shopId = null)
    {
        /** @var MollieApiFactory $apiFactory */
        $apiFactory = Shopware()->Container()->get('mollie_shopware.api_factory');

        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('mollie_shopware.components.logger');


        if ($apiFactory === null) {
            return null;
        }

        try {

            return $apiFactory->create($shopId);

        } catch (ApiException $e) {

            $logger->error(
                'Could not create an API client.',
                array(
                    'error' => $e->getMessage(),
                )
            );

            throw new \Exception('Could not create an API client.');
        }

        return null;
    }

}
