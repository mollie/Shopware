<?php

namespace MollieShopware\Traits;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Logger;
use MollieShopware\Components\MollieApiFactory;

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

        if ($apiFactory === null) {
            return null;
        }

        try {

            return $apiFactory->create($shopId);

        } catch (ApiException $e) {
            Logger::log(
                'error',
                'Could not create an API client.',
                $e,
                true
            );
        }

        return null;
    }

}
