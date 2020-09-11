<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use Enlight_Controller_Request_Request;
use Enlight_View;
use MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton;
use MollieShopware\Components\Country\CountryIsoParser;
use Shopware\Models\Shop\Shop;

class ApplePayButtonBuilder
{

    /**
     * This is the smarty view variable that will be
     * used as key in the storefront.
     * It contains all required data for apple pay direct.
     */
    const KEY_MOLLIE_APPLEPAY_BUTTON = 'sMollieApplePayDirectButton';


    /**
     * @var \sAdmin
     */
    private $sAdmin;

    /**
     * @var ApplePayPaymentMethod
     */
    private $applePayPaymentMethod;


    /**
     * @param ApplePayPaymentMethod $applePayPaymentMethod
     */
    public function __construct(ApplePayPaymentMethod $applePayPaymentMethod)
    {
        # attention, modules does not exist in CLI
        $this->sAdmin = Shopware()->Modules()->Admin();
        $this->applePayPaymentMethod = $applePayPaymentMethod;
    }


    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_View $view
     * @param Shop $shop
     */
    public function addButtonStatus(Enlight_Controller_Request_Request $request, Enlight_View $view, Shop $shop)
    {
        /** @var string $controller */
        $controller = strtolower($request->getControllerName());

        # apple pay requires a country iso
        # we use the first one from our country list.
        # this list is already configured for the current shop.
        # we also just use the first one, because we don't know the
        # country of the anonymous user at that time
        $activeCountries = $this->sAdmin->sGetCountryList();
        $firstCountry = array_shift($activeCountries);
        $isoParser = new CountryIsoParser();
        $country = $isoParser->getISO($firstCountry);

        $button = new ApplePayButton(
            $this->applePayPaymentMethod->isApplePayDirectEnabled(),
            $country,
            $shop->getCurrency()->getCurrency()
        );


        # now decide if we want to set our button to "item mode".
        # this means, that only this item will be sold
        # when using the apple pay direct checkout
        if ($controller === 'detail') {
            $vars = $view->getAssign();
            $button->setItemMode($vars["sArticle"]["ordernumber"]);
        }

        $view->assign(self::KEY_MOLLIE_APPLEPAY_BUTTON, $button->toArray());
    }

}
