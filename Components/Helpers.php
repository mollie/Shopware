<?php

// Mollie Shopware Plugin Version: 1.4.6

namespace MollieShopware\Components;

class Helpers
{
    /**
     * Check if a string contains a substring
     *
     * @param  string $haystack String to find substring in
     * @param  string $needle   Substring to find
     * @return string
     */
    public static function stringContains($haystack, $needle)
    {
        return mb_stripos($haystack, $needle) !== false;
    }
}
