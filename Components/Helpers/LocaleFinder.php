<?php

namespace MollieShopware\Components\Helpers;

class LocaleFinder
{

    /**
     * @return string
     */
    public function getPaymentLocale($locale)
    {
        // mollie locales
        $mollieLocales = [
            'en_US',
            'nl_NL',
            'fr_FR',
            'it_IT',
            'de_DE',
            'de_AT',
            'de_CH',
            'es_ES',
            'ca_ES',
            'nb_NO',
            'pt_PT',
            'sv_SE',
            'fi_FI',
            'da_DK',
            'is_IS',
            'hu_HU',
            'pl_PL',
            'lv_LV',
            'lt_LT',
        ];


        // set default locale on empty or not supported shop locale
        if (empty($locale) || !in_array($locale, $mollieLocales)) {
            $locale = 'en_US';
        }

        return $locale;
    }
}
