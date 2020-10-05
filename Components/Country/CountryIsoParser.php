<?php

namespace MollieShopware\Components\Country;

class CountryIsoParser
{

    /**
     * This function extracts the ISO value from the country.
     * Unfortunately different shopware versiosn have different ISO key names.
     * So we just check what exists.
     *
     * @param array $country
     * @return mixed|string
     */
    public function getISO($country)
    {
        if (array_key_exists('iso', $country)) {
            return $country['iso'];
        }

        if (array_key_exists('countryiso', $country)) {
            return $country['countryiso'];
        }

        return '';
    }

}
