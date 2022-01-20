<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use Doctrine\ORM\EntityNotFoundException;
use Enlight_Controller_Request_Request;
use Enlight_View;
use MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton;
use MollieShopware\Components\ApplePayDirect\Models\Button\DisplayOption;
use MollieShopware\Components\Config;
use MollieShopware\Components\Country\CountryIsoParser;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Validator\Constraints\NotBlank;

class ApplePayButtonBuilder
{

    /**
     * This is the smarty view variable that will be
     * used as key in the storefront.
     * It contains all required data for apple pay direct.
     */
    const KEY_MOLLIE_APPLEPAY_BUTTON = 'sMollieApplePayDirectButton';


    /**
     * @var Config
     */
    private $configMollie;

    /**
     * @var \Shopware_Components_Config
     */
    private $configShopware;

    /**
     * @var \sAdmin
     */
    private $sAdmin;

    /**
     * @var ApplePayPaymentMethod
     */
    private $applePayPaymentMethod;

    /**
     * @var ApplePayDirectDisplayOptions
     */
    private $restrictionService;


    /**
     * @param Config $configMollie
     * @param \Shopware_Components_Config $configShopware
     * @param ApplePayPaymentMethod $applePayPaymentMethod
     * @param ApplePayDirectDisplayOptions $restrictionService
     */
    public function __construct(Config $configMollie, \Shopware_Components_Config $configShopware, ApplePayPaymentMethod $applePayPaymentMethod, ApplePayDirectDisplayOptions $restrictionService)
    {
        $this->configMollie = $configMollie;
        $this->configShopware = $configShopware;
        $this->restrictionService = $restrictionService;

        # attention, modules does not exist in CLI
        $this->sAdmin = Shopware()->Modules()->Admin();
        $this->applePayPaymentMethod = $applePayPaymentMethod;
    }


    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_View $view
     * @param Shop $shop
     * @throws \Exception
     */
    public function addButtonStatus(Enlight_Controller_Request_Request $request, Enlight_View $view, Shop $shop)
    {
        /** @var string $controller */
        $controller = strtolower($request->getControllerName());


        $isActive = $this->applePayPaymentMethod->isApplePayDirectEnabled();

        if ($isActive) {
            # now verify the risk management.
            # the merchant might have some settings for risk management.
            # if so, then block the buttons from being used
            $isRiskManagementBlocked = $this->applePayPaymentMethod->isRiskManagementBlocked($this->sAdmin);

            if ($isRiskManagementBlocked) {
                $isActive = false;
            }
        }


        # apple pay requires a country iso
        # we use the first one from our country list.
        # this list is already configured for the current shop.
        # we also just use the first one, because we don't know the
        # country of the anonymous user at that time
        $activeCountries = $this->sAdmin->sGetCountryList();
        $firstCountry = array_shift($activeCountries);
        $isoParser = new CountryIsoParser();
        $country = $isoParser->getISO($firstCountry);

        # the shopware shop might be configured to require a phone field
        # in this case, that requirement is passed on to apple pay as well
        $requirePhoneNumber = $this->configShopware->offsetGet('requirePhoneField');

        $button = new ApplePayButton(
            $isActive,
            $country,
            $shop->getCurrency()->getCurrency(),
            $requirePhoneNumber
        );


        # add our custom restrictions so that
        # we know when the button must not be displayed
        $pluginRestrictions = $this->configMollie->getApplePayDirectRestrictions();

        /** @var DisplayOption $option */
        foreach ($this->restrictionService->getDisplayOptions() as $option) {
            # see if we have it restricted in our plugin
            $isRestricted = in_array($option->getId(), $pluginRestrictions, true);
            # add our restriction settings
            $button->addDisplayOption($option, $isRestricted);
        }

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
