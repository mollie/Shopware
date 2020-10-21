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
        $restrictions[] = new DisplayOption(2, 'cart', 'Cart');
        $restrictions[] = new DisplayOption(3, 'cart_offcanvas', 'Cart (Offcanvas)');

        return $restrictions;
    }

}
