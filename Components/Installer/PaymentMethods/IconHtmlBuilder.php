<?php

namespace MollieShopware\Components\Installer\PaymentMethods;

use Mollie\Api\Resources\Method;

class IconHtmlBuilder
{

    /**
     * @param Method $method
     * @return string
     */
    public function getIconHTML(Method $method)
    {
        return '<img src="' . $method->image->size1x . '" alt="' . $method->description . '" class="mollie-payment-icon" />';
    }

}
