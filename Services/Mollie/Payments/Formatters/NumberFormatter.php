<?php

namespace MollieShopware\Services\Mollie\Payments\Formatters;

class NumberFormatter
{

    /**
     * @param float $value
     * @return string
     */
    public function formatNumber($value)
    {
        return number_format($value, 2, '.', '');
    }
}
