<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use Doctrine\ORM\EntityNotFoundException;
use Enlight_Controller_Request_Request;
use Enlight_View;
use Exception;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\ApplePayDirect\Models\Button\ApplePayButton;
use MollieShopware\Components\ApplePayDirect\Models\Button\DisplayOption;
use MollieShopware\Components\Config;
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

    /** @var Account */
    private $accountService;

    /**
     * Don't use $this->sBasket directly,
     * use $this->getBasket() instead.
     *
     * @var \sBasket
     */
    private $sBasket;

    /**
     * @var Config
     */
    private $configMollie;

    /**
     * @var \Shopware_Components_Config
     */
    private $configShopware;

    /**
     * Don't use $this->sAdmin directly,
     * use $this->getAdmin() instead.
     *
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
     * @param Account $accountService
     * @param Config $configMollie
     * @param \Shopware_Components_Config $configShopware
     * @param ApplePayPaymentMethod $applePayPaymentMethod
     * @param ApplePayDirectDisplayOptions $restrictionService
     */
    public function __construct(Account $accountService, Config $configMollie, \Shopware_Components_Config $configShopware, ApplePayPaymentMethod $applePayPaymentMethod, ApplePayDirectDisplayOptions $restrictionService)
    {
        $this->accountService = $accountService;
        $this->applePayPaymentMethod = $applePayPaymentMethod;
        $this->configMollie = $configMollie;
        $this->configShopware = $configShopware;
        $this->restrictionService = $restrictionService;
    }

    /**
     * Sets the admin module in this class.
     *
     * @param \sAdmin $admin
     * @return self
     */
    public function setAdmin(\sAdmin $admin)
    {
        $this->sAdmin = $admin;
        return $this;
    }

    /**
     * Sets the basket module in this class.
     *
     * @param \sBasket $basket
     * @return self
     */
    public function setBasket(\sBasket $basket)
    {
        $this->sBasket = $basket;
        return $this;
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_View $view
     * @param Shop $shop
     * @throws Exception
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
            $isRiskManagementBlocked = $this->applePayPaymentMethod->isRiskManagementBlocked($this->getAdmin());

            if ($isRiskManagementBlocked) {
                $isActive = false;
            }

            # check if the customer can use Apple Pay direct for ESD products
            if ($this->isBlockedForEsd($controller, $view)) {
                $isActive = false;
            }
        }

        # apple pay requires a country iso
        # we use the first one from our country list.
        # this list is already configured for the current shop.
        # we also just use the first one, because we don't know the
        # country of the anonymous user at that time
        $activeCountries = $this->getAdmin()->sGetCountryList();
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

    /**
     * Returns the admin module, loads it from
     * the singleton if it's not set.
     *
     * @return \sAdmin
     */
    private function getAdmin()
    {
        # attention, modules does not exist in CLI
        if (!isset($this->sAdmin)) {
            $this->sAdmin = Shopware()->Modules()->Admin();
        }

        return $this->sAdmin;
    }

    /**
     * Returns the basket module, loads it from
     * the singleton if it's not set.
     *
     * @return \sBasket
     */
    private function getBasket()
    {
        # attention, modules does not exist in CLI
        if (!isset($this->sBasket)) {
            $this->sBasket = Shopware()->Modules()->Basket();
        }

        return $this->sBasket;
    }

    /**
     * Returns if the basket has ESD products.
     *
     * @return bool
     */
    private function hasEsdProductsInBasket()
    {
        return $this->getBasket()->sCheckForESD();
    }

    /**
     * Returns if the Apple Pay direct button
     * is blocked for ESD products.
     *
     * @param string $controllerName
     * @param Enlight_View $view
     * @return bool
     */
    private function isBlockedForEsd($controllerName, Enlight_View $view)
    {
        # if a customer has esd products in the basket, the
        # Apple Pay direct button is only available if the
        # customer is fully logged in (not as guest)
        $hasEsdProductsInBasket = $controllerName === 'checkout' && $this->hasEsdProductsInBasket();
        $isEsdProductDetailPage = $controllerName === 'detail' && $this->isEsdProductPage($view);
        $isUserLoggedIn = $this->accountService->isLoggedIn() && !$this->accountService->isLoggedInAsGuest();

        return ($hasEsdProductsInBasket || $isEsdProductDetailPage) && !$isUserLoggedIn;
    }

    /**
     * Returns if the product page is for an ESD product.
     *
     * @param Enlight_View $view
     * @return bool
     */
    private function isEsdProductPage(Enlight_View $view)
    {
        return boolval($view->getAssign('sArticle')['esd']) === true;
    }
}
