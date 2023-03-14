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
        if (strtoupper($currencyIso) === self::CURRENCY_JPY) {
            return number_format($value, 0, '.', '');
        }
        return number_format($value, 2, '.', '');
    }
}
