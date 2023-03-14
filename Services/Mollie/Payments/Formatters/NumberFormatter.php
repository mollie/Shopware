<?php

namespace MollieShopware\Services\Mollie\Payments\Formatters;

class NumberFormatter
{
    const CURRENCY_JPY = 'JPY';

    /**
     * @param float $value
     * @param string $currencyIso
     * @return string
     */
    public function formatNumber($value, $currencyIso)
    {
        $decimals = 2;
        if (strtoupper($currencyIso) === self::CURRENCY_JPY) {
            $decimals = 0;
        }
        return number_format($value, $decimals, '.', '');
    }
}
