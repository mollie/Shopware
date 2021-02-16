<?php

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectFactory;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\ApplePayDirect\Models\UserData\UserData;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayFormatter;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\BasketSnapshot\BasketSnapshot;
use MollieShopware\Components\Config;
use MollieShopware\Components\Country\CountryIsoParser;
use MollieShopware\Components\Order\OrderAddress;
use MollieShopware\Components\Order\OrderSession;
use MollieShopware\Components\Shipping\Shipping;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_MollieApplePayDirect extends Shopware_Controllers_Frontend_Checkout implements CSRFWhitelistAware
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

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
     * @var Config
     */
    private $config;


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
        $this->logger = Shopware()->Container()->get('mollie_shopware.components.logger');
        $this->eventManager = Shopware()->Container()->get('events');
        $this->shopContext = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();

        $this->shipping = Shopware()->Container()->get('mollie_shopware.components.shipping');
        $this->orderSession = Shopware()->Container()->get('mollie_shopware.components.order_session');
        $this->account = Shopware()->Container()->get('mollie_shopware.components.account.account');
        $this->basketSnapshot = Shopware()->Container()->get('mollie_shopware.components.basket_snapshot.basket_snapshot');

        $this->config = Shopware()->Container()->get('mollie_shopware.config');

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

            $this->logger->error(
                'Error when adding product to apple pay cart',
                array(
                    'error' => $ex->getMessage()
                )
            );

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
            $shippingMethods = $this->eventManager->filter(
                'Mollie_ApplePayDirect_getShippings_FilterResult',
                $shippingMethods,
                array(
                    'country' => $countryCode
                )
            );


            $cart = $this->handlerApplePay->buildApplePayCart();
            $formattedCart = $this->applePayFormatter->formatCart(
                $cart,
                Shopware()->Shop(),
                $this->config->isTestmodeActive()
            );


            $data = array(
                'success' => true,
                'cart' => $formattedCart,
                'shippingmethods' => $shippingMethods,
            );

            echo json_encode($data);
            die();

        } catch (Throwable $ex) {

            $this->logger->error(
                'Error loading shippings for Mollie Apple Pay Direct',
                array(
                    'error' => $ex->getMessage()
                )
            );

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
            $formattedCart = $this->applePayFormatter->formatCart(
                $cart,
                Shopware()->Shop(),
                $this->config->isTestmodeActive()
            );

            $data = array(
                'success' => true,
                'cart' => $formattedCart,
            );

            echo json_encode($data);
            die();

        } catch (Throwable $ex) {

            $this->logger->error(
                'Error setting shipping for Mollie Apple Pay Direct',
                array(
                    'error' => $ex->getMessage()
                )
            );

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

            $this->logger->error(
                'Error restoring cart after Mollie Apple Pay Direct',
                array(
                    'error' => $ex->getMessage()
                )
            );

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

            $this->logger->error(
                'Error starting Mollie Apple Pay Direct session',
                array(
                    'error' => $ex->getMessage()
                )
            );

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


            # now check if we are already signed in
            # if so, then we can just continue, because sessions are already set.
            # if we are not signed in, then we need to
            # create a new guest account and login as that one
            if (!$this->account->isLoggedIn()) {

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
            }


            # create our user data object
            # that we need later to set our custom data
            # in case our registered user has different billing data
            $userData = new UserData(
                $email,
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode
            );
            $this->handlerApplePay->setUserData($userData);

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

            $this->logger->error(
                'Error starting Mollie Apple Pay Direct payment',
                array(
                    'error' => $ex->getMessage()
                )
            );

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


            /** @var UserData|null $userData */
            $userData = $this->handlerApplePay->getUserData();

            # check if we have a valid user data object
            # if we have it, then modify our session data before creating the order.
            # Why - because of guidelines!
            # We might have a signed in user with a different billing address,
            # so we must always use the data from the Apple Pay payment sheet!
            if ($userData instanceof UserData) {

                /** @var array $country */
                $country = $this->getCountry($userData->getCountryCode());

                if ($country === null) {
                    throw new Exception('No Country found for code ' . $userData->getCountryCode());
                }

                # now convert to our address object
                # which is passed on to our order session component
                $address = new OrderAddress(
                    $userData->getFirstname(),
                    $userData->getLastname(),
                    $userData->getStreet(),
                    $userData->getZipcode(),
                    $userData->getCity(),
                    $country
                );

                # use our data in the order later on
                $this->orderSession->setCustomerData($this, $address);
            }

            # now prepare the session such as the user
            # would have done it by browsing in the shop.
            # instead of this, our prepared and simulated data
            # will be used for this.
            $this->orderSession->prepareOrderSession(
                $this,
                $this->applePayPaymentMethod->getPaymentMethod(),
                $this->shopContext
            );

            # clear our session data
            $this->handlerApplePay->clearUserData();


            # redirect to our centralized mollie
            # direct controller action
            $this->redirect(
                [
                    'controller' => 'Mollie',
                    'action' => 'direct',
                ]
            );

        } catch (Throwable $ex) {

            $this->logger->error(
                'Error finishing Mollie Apple Pay Direct payment',
                array(
                    'error' => $ex->getMessage()
                )
            );

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
