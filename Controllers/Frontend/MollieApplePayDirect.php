<?php

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectFactory;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayFormatter;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\BasketSnapshot\BasketSnapshot;
use MollieShopware\Components\Country\CountryIsoParser;
use MollieShopware\Components\Logger;
use MollieShopware\Components\Order\OrderSession;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Traits\MollieApiClientTrait;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_MollieApplePayDirect extends Shopware_Controllers_Frontend_Checkout implements CSRFWhitelistAware
{

    use MollieApiClientTrait;


    /**
     * @var ContainerAwareEventManager $eventManager
     */
    private $eventManager;

    /**
     * @var ApplePayDirectHandlerInterface $handlerApplePay
     */
    private $handlerApplePay;

    /**
     * @var ApplePayPaymentMethod
     */
    private $applePayPaymentMethod;

    /**
     * @var ApplePayFormatter
     */
    private $applePayFormatter;

    /**
     * @var Shipping $shipping
     */
    private $shipping;

    /**
     * @var OrderSession
     */
    private $orderSession;

    /**
     * @var ShopContext
     */
    private $shopContext;

    /**
     * @var Account
     */
    private $account;

    /**
     * @var BasketSnapshot
     */
    private $basketSnapshot;


    /**
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'startPayment',
            'finishPayment',
        ];
    }


    /**
     * Our controller isn't created with XML services and DI.
     * It all works different in Shopware 5,
     * so we just inject this function in our actions to
     * load all our services correctly.
     *
     * @throws ApiException
     */
    private function loadServices()
    {
        $this->eventManager = Shopware()->Container()->get('events');
        $this->shopContext = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();

        $this->shipping = Shopware()->Container()->get('mollie_shopware.components.shipping');
        $this->orderSession = Shopware()->Container()->get('mollie_shopware.components.order_session');
        $this->account = Shopware()->Container()->get('mollie_shopware.components.account.account');
        $this->basketSnapshot = Shopware()->Container()->get('mollie_shopware.components.basket_snapshot.basket_snapshot');

        $this->applePayPaymentMethod = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.services.payment_method');
        $this->applePayFormatter = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.services.formatter');

        /** @var ApplePayDirectFactory $applePayFactory */
        $applePayFactory = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.factory');
        $this->handlerApplePay = $applePayFactory->createHandler();
    }

    /**
     * This route adds the provided article
     * to the cart. It will first create a snapshot of the current
     * cart, then it will delete it and only add our single product to it.
     *
     * @throws Exception
     */
    public function addProductAction()
    {
        try {

            $this->loadServices();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

            // we start by creating a snapshot
            // of our cart
            $this->basketSnapshot->createSnapshot($this->basket);

            // delete the cart,
            // to make sure that only the selected product is transferred to Apple Pay
            $this->basket->sDeleteBasket();

            $productNumber = $this->Request()->getParam('number');
            $productQuantity = $this->Request()->getParam('quantity');

            $this->basket->sAddArticle($productNumber, $productQuantity);

            // add potential discounts or surcharges to prevent an amount mismatch
            // on patching the new amount after the confirmation.
            // only necessary if the customer directly checks out from product detail page
            $countries = $this->admin->sGetCountryList();
            $this->admin->sGetPremiumShippingcosts(reset($countries));

            echo "";
            die();

        } catch (Throwable $ex) {
            Logger::log('error', 'Error when adding product to apple pay cart', $ex->getMessage(), $ex);

            http_response_code(500);
            die();
        }
    }

    /**
     * This route returns a JSON with the current
     * cart and all available shipping methods for the
     * provided country.
     * The shipping methods have to be configured for
     * Apple Pay Direct and the country.
     * The code will also lookup the shipping costs for each method.
     */
    public function getShippingsAction()
    {
        try {

            $this->loadServices();


            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

            /** @var string $countryCode */
            $countryCode = $this->Request()->getParam('countryCode');

            /** @var array $userCountry */
            $userCountry = $this->getCountry($countryCode);

            if ($userCountry === null) {
                throw new Exception('Country ' . $countryCode . ' is not supported and active.');
            }

            # set the current country in our session
            # to the one, from the apple pay sheet.
            $this->session->offsetSet('sCountry', $userCountry['id']);

            /** @var int $applePayMethodId */
            $applePayMethodId = $this->applePayPaymentMethod->getPaymentMethod()->getId();

            # get all available shipping methods
            # for apple pay direct and our selected country
            $dispatchMethods = $this->shipping->getShippingMethods($userCountry['id'], $applePayMethodId);

            # now build an apple pay conform array
            # of these shipping methods
            $shippingMethods = $this->prepareShippingMethods($dispatchMethods, $userCountry);


            # fire event about the shipping methods that
            # will be returned for the country
            $this->eventManager->filter(
                'Mollie_ApplePayDirect_getShippings_FilterResult',
                $shippingMethods,
                array(
                    'country' => $countryCode
                )
            );


            $cart = $this->handlerApplePay->buildApplePayCart();
            $formattedCart = $this->applePayFormatter->formatCart($cart, Shopware()->Shop());


            $data = array(
                'success' => true,
                'cart' => $formattedCart,
                'shippingmethods' => $shippingMethods,
            );

            echo json_encode($data);
            die();

        } catch (Throwable $ex) {
            Logger::log('error', 'Error loading shippings for Mollie Apple Pay Direct: ' . $ex->getMessage(), $ex);

            $data = array(
                'success' => false,
            );

            echo json_encode($data);
            die();
        }
    }

    /**
     * This route sets the provided shipping method
     * as the one that will be used for the cart.
     * It then returns the cart as JSON along
     * with the used shipping identifier.
     */
    public function setShippingAction()
    {
        try {

            $this->loadServices();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

            $shippingIdentifier = $this->Request()->getParam('identifier', '');


            # fire event about the shipping methods that
            # will be set for the user
            $this->eventManager->filter(
                'Mollie_ApplePayDirect_setShipping_FilterResult',
                $shippingIdentifier,
                array()
            );


            if (!empty($shippingIdentifier)) {
                $this->shipping->setCartShippingMethodID($shippingIdentifier);
            }

            $cart = $this->handlerApplePay->buildApplePayCart();
            $formattedCart = $this->applePayFormatter->formatCart($cart, Shopware()->Shop());

            $data = array(
                'success' => true,
                'cart' => $formattedCart,
            );

            echo json_encode($data);
            die();

        } catch (Throwable $ex) {
            Logger::log('error', 'Error setting shipping for Mollie Apple Pay Direct: ' . $ex->getMessage(), $ex);

            $data = array(
                'success' => false,
            );

            echo json_encode($data);
            die();
        }
    }

    /**
     * This route restores the cart and
     * adds all items again that where previously
     * added before starting Apple Pay.
     */
    public function restoreCartAction()
    {
        try {

            $this->loadServices();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

            $this->basketSnapshot->restoreSnapshot($this->basket);

            // add potential discounts or surcharges to prevent an amount mismatch
            // on patching the new amount after the confirmation.
            // only necessary if the customer directly checks out from product detail page
            $countries = $this->admin->sGetCountryList();
            $this->admin->sGetPremiumShippingcosts(reset($countries));

            echo "";
            die();

        } catch (Throwable $ex) {
            Logger::log('error', 'Error restoring cart after Mollie Apple Pay Direct: ' . $ex->getMessage(), $ex);

            http_response_code(500);
            die();
        }
    }

    /**
     * This route starts a new merchant validation that
     * is required to start an apple pay session checkout.
     * It will use Mollie as proxy to talk to Apple.
     * The resulting session data must then be output
     * exactly as it has been received.
     *
     * @return mixed
     * @throws Exception
     */
    public function createPaymentSessionAction()
    {
        try {

            $this->loadServices();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

            $domain = Shopware()->Shop()->getHost();
            $validationUrl = (string)$this->Request()->getParam('validationUrl');

            /** @var string $response */
            $response = $this->handlerApplePay->requestPaymentSession($domain, $validationUrl);

            echo $response;

        } catch (Exception $ex) {

            Logger::log('error', 'Error starting Mollie Apple Pay Direct session: ' . $ex->getMessage(), $ex);

            http_response_code(500);
            die();
        }
    }

    /**
     * This route is the last part of processing an apple pay direct payment.
     * It will receive the payment token from the client
     * and continue with the server side checkout process.
     *
     * @throws Exception
     */
    public function startPaymentAction()
    {
        try {

            $this->loadServices();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();


            $email = $this->Request()->getParam('email', '');
            $firstname = $this->Request()->getParam('firstname', '');
            $lastname = $this->Request()->getParam('lastname', '');

            $street = $this->Request()->getParam('street', '');
            $zipcode = $this->Request()->getParam('postalCode', '');
            $city = $this->Request()->getParam('city', '');
            $countryCode = $this->Request()->getParam('countryCode', '');

            /** @var array $country */
            $country = $this->getCountry($countryCode);

            if ($country === null) {
                throw new Exception('No Country found for code ' . $countryCode);
            }


            $this->account->createGuestAccount(
                $email,
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $country['id']
            );

            $this->account->loginAccount($email);


            # save our payment token
            # that will be used when creating the
            # payment in the mollie controller action
            $paymentToken = $this->Request()->getParam('paymentToken', '');
            $this->handlerApplePay->setPaymentToken($paymentToken);

            # redirect to our finish action
            # on that action the new guest user is fully loaded
            # into our view variables and we can continue with
            # preparing the order in our session and finishing the checkout
            $this->redirect(
                [
                    'controller' => 'MollieApplePayDirect',
                    'action' => 'finishPayment',
                ]
            );

        } catch (Throwable $ex) {

            Logger::log('error', 'Error starting Mollie Apple Pay Direct payment: ' . $ex->getMessage(), $ex);

            http_response_code(500);
            die();
        }
    }

    /**
     * @throws Exception
     */
    public function finishPaymentAction()
    {
        try {

            $this->loadServices();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

            $this->orderSession->prepareOrderSession(
                $this,
                $this->applePayPaymentMethod->getPaymentMethod(),
                $this->shopContext
            );

            # redirect to our centralized mollie
            # direct controller action
            $this->redirect(
                [
                    'controller' => 'Mollie',
                    'action' => 'direct',
                ]
            );

        } catch (Throwable $ex) {

            Logger::log('error', 'Error finishing Mollie Apple Pay Direct payment: ' . $ex->getMessage(), $ex);

            http_response_code(500);
            die();
        }
    }

    /**
     * @param $countryCode
     * @return array|null
     */
    private function getCountry($countryCode)
    {
        $countries = $this->admin->sGetCountryList();

        $foundCountry = null;

        $isoParser = new CountryIsoParser();

        /** @var array $country */
        foreach ($countries as $country) {

            /** @var string $iso */
            $iso = $isoParser->getISO($country);

            if (strtolower($iso) === strtolower($countryCode)) {
                $foundCountry = $country;
                break;
            }
        }

        return $foundCountry;
    }

    /**
     * @param array $dispatchMethods
     * @param $userCountry
     * @return array
     */
    private function prepareShippingMethods(array $dispatchMethods, $userCountry)
    {
        $selectedMethod = null;
        $otherMethods = array();

        $selectedMethodID = $this->shipping->getCartShippingMethodID();

        /** @var array $method */
        foreach ($dispatchMethods as $method) {

            /** @var float $costs */
            $costs = $this->shipping->getShippingMethodCosts($userCountry, $method['id']);

            /** @var array $formatted */
            $formatted = $this->applePayFormatter->formatShippingMethod($method, $costs);

            if ($selectedMethodID === $method['id']) {
                $selectedMethod = $formatted;
            } else {
                $otherMethods[] = $formatted;
            }
        }

        $shippingMethods = array();

        if ($selectedMethod !== null) {
            $shippingMethods[] = $selectedMethod;
        } else {
            # set first one as default
            foreach ($otherMethods as $method) {
                $this->shipping->setCartShippingMethodID($method['identifier']);
                break;
            }
        }

        foreach ($otherMethods as $method) {
            $shippingMethods[] = $method;
        }

        return $shippingMethods;
    }

}
