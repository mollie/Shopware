<?php

namespace MollieShopware\Components\Mollie;

use Exception;
use Mollie\Api\MollieApiClient;


class MollieApiTester
{

    /**
     * Gets if the provided client can
     * successfully connect to the Mollie API using
     * the configured API keys.
     *
     * @param MollieApiClient $client
     * @return bool
     */
    public function isConnectionValid(MollieApiClient $client)
    {
        $isValid = false;

        try {

            $profile = $client->profiles->getCurrent();

            # test if the profile exists
            # if existing, our api key is valid
            if (isset($profile->id)) {
                $isValid = true;
            }

        } catch (Exception $e) {
            # no need to handle this in here
            # its just not valid
        }

        return $isValid;
    }

}
