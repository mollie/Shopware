<?php

<<<<<<< HEAD
	// Mollie Shopware Plugin Version: 1.3.1
=======
	// Mollie Shopware Plugin Version: 1.3.2
>>>>>>> order_position_fix

namespace MollieShopware\Components;

use Closure;
use InvalidArgumentException;
use Countable;
use Smarty;

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
