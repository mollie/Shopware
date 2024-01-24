<?php

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\Account\Exception\RegistrationMissingFieldException;
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
use MollieShopware\Components\Translation\FrontendTranslation;
use MollieShopware\Events\Events;
use MollieShopware\Exceptions\RiskManagementBlockedException;
use MollieShopware\Traits\Controllers\RedirectTrait;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Article\Detail;

class Shopware_Controllers_Frontend_MollieApplePayDirect extends Shopware_Controllers_Frontend_Checkout implements CSRFWhitelistAware
{
    use RedirectTrait;


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
     * @var \Shopware\Models\Detail\Repository
     */
    private $repoArticles;

    /**
     * @var FrontendTranslation
     */
    private $translation;


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
        $this->translation = Shopware()->Container()->get('mollie_shopware.components.translation.frontend');

        $this->config = Shopware()->Container()->get('mollie_shopware.config');

        /** @var ApplePayDirectFactory $applePayFactory */
        $applePayFactory = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.factory');

        $this->applePayFormatter = $applePayFactory->createFormatter();
        $this->handlerApplePay = $applePayFactory->createHandler();

        $this->applePayPaymentMethod = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.services.payment_method');

        $this->repoArticles = Shopware()->Models()->getRepository(Detail::class);
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

            // set the correct payment ID in order to add additional costs
            $this->front->Request()->setPost('sPayment', $this->applePayPaymentMethod->getPaymentMethod()->getId());

            // add potential discounts or surcharges to prevent an amount mismatch
            // on patching the new amount after the confirmation.
            // only necessary if the customer directly checks out from product detail page
            $countries = $this->admin->sGetCountryList();
            $this->admin->sGetPremiumShippingcosts(reset($countries));

            echo "";
            ob_clean();
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error when adding product to apple pay cart',
                [
                    'error' => $ex->getMessage()
                ]
            );

            http_response_code(500);
            ob_clean();
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


            $previousShippingMethods = $shippingMethods;

            # fire event about the shipping methods that
            # will be returned for the country
            $shippingMethods = $this->eventManager->filter(
                Events::APPLEPAY_DIRECT_GET_SHIPPINGS,
                $shippingMethods,
                [
                    'country' => $countryCode
                ]
            );

            if ($previousShippingMethods !== $shippingMethods) {
                # we just cant show a full long array here
                # so we at least show that something might has changed (even if the count is the same)
                $this->logger->info(
                    'Filter Event changed Apple Pay Direct Shipping Methods',
                    [
                        'message' => 'Please note that we cannot show the long full list here, even if the count is the same, the content might have changed',
                        'data' => [
                            'previousShippingsCount' => count($previousShippingMethods),
                            'newShippingsCount' => count($shippingMethods)
                        ]
                    ]
                );
            }

            $cart = $this->handlerApplePay->buildApplePayCart();
            $formattedCart = $this->applePayFormatter->formatCart(
                $cart,
                Shopware()->Shop(),
                $this->config->isTestmodeActive()
            );


            $data = [
                'success' => true,
                'cart' => $formattedCart,
                'shippingmethods' => $shippingMethods,
            ];

            echo json_encode($data);
            ob_clean();
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error loading shippings for Mollie Apple Pay Direct',
                [
                    'error' => $ex->getMessage()
                ]
            );

            $data = [
                'success' => false,
            ];

            echo json_encode($data);
            ob_clean();
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


            $previousShippingIdentifier = $shippingIdentifier;

            # fire event about the shipping methods that
            # will be set for the user
            $shippingIdentifier = $this->eventManager->filter(
                Events::APPLEPAY_DIRECT_SET_SHIPPING,
                $shippingIdentifier,
                []
            );

            if ($previousShippingIdentifier !== $shippingIdentifier) {
                $this->logger->info(
                    'Filter Event changed Apple Pay Direct Shipping Method to ' . $shippingIdentifier,
                    [
                        'data' => [
                            'previousShipping' => $previousShippingIdentifier,
                            'newShipping' => $shippingIdentifier
                        ]
                    ]
                );
            }

            if (!empty($shippingIdentifier)) {
                $this->shipping->setCartShippingMethodID($shippingIdentifier);
            }

            $cart = $this->handlerApplePay->buildApplePayCart();
            $formattedCart = $this->applePayFormatter->formatCart(
                $cart,
                Shopware()->Shop(),
                $this->config->isTestmodeActive()
            );

            $data = [
                'success' => true,
                'cart' => $formattedCart,
            ];

            echo json_encode($data);
            ob_clean();
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error setting shipping for Mollie Apple Pay Direct',
                [
                    'error' => $ex->getMessage()
                ]
            );

            $data = [
                'success' => false,
            ];

            echo json_encode($data);
            ob_clean();
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
            ob_clean();
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error restoring cart after Mollie Apple Pay Direct',
                [
                    'error' => $ex->getMessage()
                ]
            );

            http_response_code(500);
            ob_clean();
        }
    }

    /**
     * This route starts a new merchant validation that
     * is required to start an apple pay session checkout.
     * It will use Mollie as proxy to talk to Apple.
     * The resulting session data must then be output
     * exactly as it has been received.
     *
     * @throws Exception
     * @return mixed
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
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error starting Mollie Apple Pay Direct session',
                [
                    'error' => $ex->getMessage()
                ]
            );

            http_response_code(500);
            ob_clean();
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
        /** @var null|Detail $pdpArticle */
        $pdpArticle = null;
        $isPDPPurchase = false;

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
            $phone = $this->Request()->getParam('phone', '');
            $productNumber = $this->Request()->getParam('productNumber', '');

            /** @var array $country */
            $country = $this->getCountry($countryCode);

            if ($country === null) {
                throw new Exception('No Country found for code ' . $countryCode);
            }

            # we have to verify if we purchase a single product
            # from the PDP or a whole cart from the offcanvas or cart
            # we need this later to either redirect to the cart page
            # or back to the original product page
            $isPDPPurchase = trim((string)$productNumber) !== '';

            if ($isPDPPurchase) {
                # search for our product
                # if that is not found, then we have a problem anyway
                $articles = $this->repoArticles->findBy(['number' => $productNumber]);

                if (count($articles) <= 0) {
                    throw new Exception('Article with number: ' . $productNumber . ' not found!');
                }

                $pdpArticle = array_shift($articles);
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
                    $country['id'],
                    $phone
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


            # check for risk management
            # do after registering and assigning the contact user data (it might be used in the risk rules)
            # but BEFORE assigning our necessary payment token
            $isRiskManagementBlocked = $this->applePayPaymentMethod->isRiskManagementBlocked($this->admin);

            if ($isRiskManagementBlocked) {
                throw new RiskManagementBlockedException('Apple Pay Direct blocked due to Risk Management! Aborting payment action!');
            }


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
        } catch (RegistrationMissingFieldException $ex) {
            $this->logger->warning(
                'Apple Pay Direct Guest Account registration failed',
                [
                    'error' => $ex->getMessage(),
                    'missing' => $ex->getField(),
                ]
            );

            # now get our snippet translation for our error
            $errorMessage = $this->translation->getWithPlaceholder(FrontendTranslation::REGISTRATION_MISSING_FIELD, $ex->getField());

            if ($isPDPPurchase && $pdpArticle instanceof Detail) {
                # redirect the user to the PDP page
                # we don't even have a user and a cart, so we cannot even continue
                $this->redirectToPDPWithError($this, $pdpArticle->getId(), $errorMessage);
            } else {
                $this->redirectToShopwareCheckoutFailedWithError($this, $errorMessage);
            }
        } catch (RiskManagementBlockedException $ex) {
            $this->logger->notice(
                'Apple Pay Direct checkout blocked due to Risk Management',
                [
                    'error' => $ex->getMessage()
                ]
            );

            # we do have at least a guest user and a cart,
            # so redirect to the checkout page to increase the chance of
            # finishing the order ;)
            $this->redirectToShopwareCheckoutFailedWithError($this, $this->ERROR_PAYMENT_FAILED_RISKMANAGEMENT);
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error starting Mollie Apple Pay Direct payment',
                [
                    'error' => $ex->getMessage()
                ]
            );

            $this->redirectToShopwareCheckoutFailed($this);
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

            /** @var null|UserData $userData */
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
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error finishing Mollie Apple Pay Direct payment',
                [
                    'error' => $ex->getMessage()
                ]
            );

            http_response_code(500);
            ob_clean();
        }
    }

    /**
     * @param $countryCode
     * @return null|array
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
        $otherMethods = [];

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

        $shippingMethods = [];

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
