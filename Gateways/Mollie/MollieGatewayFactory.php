<?php

namespace MollieShopware\Gateways\Mollie;

use Mollie\Api\MollieApiClient;

class MollieGatewayFactory
{

    /**
     * @param MollieApiClient $client
     * @return MollieGateway
     */
    public function create(MollieApiClient $client)
    {
        return new MollieGateway($client);
    }
}
