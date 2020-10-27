<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use MollieShopware\Components\ApplePayDirect\Models\Button\DisplayOption;

class ApplePayDirectDisplayOptions
{

    /**
     * Gets a list of all available display options
     * for Apple Pay Direct.
     *
     * @return array
     */
    public function getDisplayOptions()
    {
        $restrictions = array();

        $restrictions[] = new DisplayOption(1, 'pdp', 'Product Detail Page');
        $restrictions[] = new DisplayOption(2, 'cart_offcanvas', 'Cart (Offcanvas)');
        $restrictions[] = new DisplayOption(3, 'cart_top', 'Cart (Top)');
        $restrictions[] = new DisplayOption(4, 'cart_bot', 'Cart (Bottom)');

        return $restrictions;
    }

}
