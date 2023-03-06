<?php

namespace MollieShopware\Components\Config;

class ConfigDataTypes
{

    /**
     * @param mixed $value
     * @return bool
     */
    public function getBoolValue($value)
    {
        if ($value === null) {
            return false;
        }

        $valuesYes = [
            'yes',
            'ja',
            'si',
            'sí',
            'oui'
        ];

        return in_array(strtolower((string)$value), $valuesYes);
    }
}
