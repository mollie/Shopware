<?php

namespace MollieShopware\Services\Mollie\Payments\Converters;


use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;

class AddressConverter
{

    /**
     * @param PaymentAddress $address
     * @return mixed[]
     */
    public function convertAddress(PaymentAddress $address)
    {
        return [
            'title' => (string)$address->getTitle(),
            'givenName' => (string)$address->getGivenName(),
            'familyName' => (string)$address->getFamilyName(),
            'email' => (string)$address->getEmail(),
            'streetAndNumber' => (string)$address->getStreet(),
            'streetAdditional' => (string)$address->getStreetAdditional(),
            'postalCode' => (string)$address->getPostalCode(),
            'city' => (string)$address->getCity(),
            'country' => (string)$address->getCountryIso2(),
        ];
    }

}
