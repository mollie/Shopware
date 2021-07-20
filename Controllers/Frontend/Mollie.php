<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Profile;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\Base\AbstractPaymentController;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Config;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Components\Helpers\LocaleFinder;
use MollieShopware\Components\Helpers\MollieRefundStatus;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\iDEAL\iDEALInterface;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Order\OrderCancellation;
use MollieShopware\Components\Order\ShopwareOrderBuilder;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Components\TransactionBuilder\TransactionBuilder;
use MollieShopware\Facades\CheckoutSession\CheckoutSessionFacade;
use MollieShopware\Facades\FinishCheckout\FinishCheckoutFacade;
use MollieShopware\Facades\FinishCheckout\RestoreSessionFacade;
use MollieShopware\Facades\FinishCheckout\Services\ConfirmationMail;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Facades\FinishCheckout\Services\ShopwareOrderUpdater;
use MollieShopware\Facades\Notifications\Notifications;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Services\TokenAnonymizer\TokenAnonymizer;
use MollieShopware\Traits\Controllers\RedirectTrait;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;

class Shopware_Controllers_Frontend_Mollie extends AbstractPaymentController
{

    use RedirectTrait;


    const ERROR_PAYMENT_FAILED = 'Payment failed';

    const TOKEN_ANONYMIZER_PLACEHOLDER_COUNT = 4;

    const TOKEN_ANONYMIZER_MAX_LENGTH = 15;


    /**
     * @var Config
     */
    private $config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutSessionFacade
     */
    private $checkout;

    /**
     * @var FinishCheckoutFacade
     */
    private $checkoutReturn;

    /**
     * @var RestoreSessionFacade
     */
    private $restoreSessionFacade;

    /**
     * @var Notifications
     */
    private $notifications;

    /**
     * @var OrderCancellation
     */
    private $orderCancellation;

    /**
     * @var ApplePayDirectHandlerInterface
     */
    private $applePay;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LocaleFinder
     */
    private $localeFinder;

    /**
     * @var iDEALInterface
     */
    private $iDEAL;

    /**
     * @var CurrentCustomer
     */
    private $customers;


    /**
     * Whitelist webhookAction from CSRF protection
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return ['notify', 'return'];
    }

    /**
     * @throws Exception
     */
    public function indexAction()
    {
        $this->redirect(
            [
                'action' => 'direct',
                'forceSecure' => true,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function directAction()
    {
        try {

            $this->loadServices();

            # persist the basket in the database
            # by saving it from the session
            $signature = $this->doPersistBasket();

            $currency = method_exists($this, 'getCurrencyShortName') ? $this->getCurrencyShortName() : 'EUR';

            # we need to extract the IDs here, because we need to
            # send the correct billing and shipping data to Mollie.
            # there is no order entity existing at this step, so we
            # need to get that data from here.
            $billingAddressID = (int)$this->getUser()['billingaddress']['id'];
            $shippingAddressID = (int)$this->getUser()['shippingaddress']['id'];


            # create a new checkout session
            # by preparing transactions, orders and more
            $session = $this->checkout->startCheckoutSession(
                $this->getBasketUserId(),
                $this->getPaymentShortName(),
                $signature,
                $currency,
                Shopware()->Shop()->getId(),
                $billingAddressID,
                $shippingAddressID
            );

            # some payment methods do not require a redirect to mollie.
            # these are automatically approved and thus we
            # have to immediately redirect to the finish action.
            if (!$session->isRedirectToMollieRequired()) {
                $this->redirect(
                    [
                        'controller' => 'Mollie',
                        'action' => 'finish',
                        'transactionNumber' => $session->getTransaction()->getId(),
                        'express' => true,
                    ]
                );
                return;
            }

            $this->redirect($session->getCheckoutUrl());
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error when starting Mollie checkout',
                [
                    'error' => $ex->getMessage()
                ]
            );

            # in theory this is not catched,
            # but for a better code understanding, we keep it here
            if ($this->checkout->getRestorableOrder() instanceof Order) {
                $this->orderCancellation->cancelAndRestoreByOrder($this->checkout->getRestorableOrder());
            }

            $this->redirectToShopwareCheckoutFailed($this);
        } finally {

            # we always have to immediately clear the token in SUCCESS or FAILURE ways
            $this->applePay->setPaymentToken('');
        }
    }

    /**
     * Returns the user from Mollie's checkout and processes his payment.
     * We start to verify if we still have a session or if we need to
     * restore the previous session from our payment token.
     * Afterwards we ALWAYS redirect to the finishAction.
     * This is done, because Shopware does automatically handle session restoring
     * from tokens after the next redirect in case we need to restore it.
     * Also the finishAction handles everything regarding payments and cancellations on errors.
     *
     * @throws Exception
     */
    public function returnAction()
    {
        $transactionID = '';

        try {

            $this->loadServices();

            $transactionID = (string)$this->Request()->getParam('transactionNumber', '');
            $paymentToken = (string)$this->Request()->getParam('token', '');

            if (empty($transactionID)) {
                throw new Exception('Missing Transaction Number');
            }

            $this->logger->debug('User is returning from Mollie for Transaction ' . $transactionID);

            $this->restoreSessionFacade->tryRestoreSession(
                (int)$transactionID,
                $paymentToken
            );

        } catch (\Exception $ex) {
            $this->logger->warning(
                'Error when verifying Session for Transaction: ' . $transactionID,
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        $this->redirectToMollieFinishPayment($this, $transactionID);
    }

    /**
     * This action finishes the actual payment process.
     * We can always assume that we have a valid session in here because we
     * are redirected from the returnAction which restores sessions if it
     * got lost somehow.
     *
     * @throws Exception
     */
    public function finishAction()
    {
        $transactionID = '';
        $hasSession = false;

        try {

            $this->loadServices();

            $transactionID = (string)$this->Request()->getParam('transactionNumber', '');
            $express = (bool)$this->Request()->getParam('express', false);

            if (empty($transactionID)) {
                throw new Exception('Missing Transaction Number');
            }

            $hasSession = $this->restoreSessionFacade->isUserSessionExisting();

            # now verify if we still have no session?!
            # shouldn't happen in expected cases, because either it's there or it has been restored!
            # we do not verify for Express checkouts, because somehow Apple Pay Direct has no session?!
            if (!$express && !$hasSession) {
                throw new Exception('Session is still missing for Transaction: ' . $transactionID . '. Shopware has already removed that user data!');
            }

            # ---------------------------------------------------------------------------------------------

            $checkoutData = $this->checkoutReturn->finishTransaction((int)$transactionID);

            # ---------------------------------------------------------------------------------------------

            $this->logger->info('Finished checkout for Transaction ' . $transactionID);

            # always make sure to clean up all data
            $this->checkoutReturn->cleanupTransaction($transactionID);

            # make sure to finish the order with the original shopware way
            $this->view->assign('orderNumber', $checkoutData->getOrdernumber());
            $this->redirectToShopwareCheckoutFinish($this, $checkoutData->getTemporaryId());

            return;

        } catch (\Exception $ex) {
            $this->logger->error(
                'Checkout failed when finishing Order for Transaction: ' . $transactionID,
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        # -----------------------------------------------------------------------------
        # USE SECOND TRY/CATCH BECAUSE OUR CATCH HAS LOGIC THAT COULD FAIL!!

        # if we had some errors make sure to
        # cancel our order and navigate back to the confirm page
        if ($hasSession && !empty($transactionID)) {

            try {

                $this->checkoutReturn->cleanupTransaction($transactionID);

                $this->orderCancellation->cancelAndRestoreByTransaction($transactionID);

            } catch (\Exception $ex) {
                $this->logger->error(
                    'Error when restoring cart on errors!',
                    [
                        'error' => $ex->getMessage(),
                    ]
                );
            }
        }

        $this->redirectToShopwareCheckoutFailed($this);
    }

    /**
     * @throws ApiException
     */
    public function notifyAction()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        try {
            $this->loadServices();

            /** @var string $transactionID */
            $transactionID = $this->Request()->getParam('transactionNumber', '');

            if (empty($transactionID)) {
                throw new \Exception('No transaction number provided!');
            }

            /** @var string $paymentID */
            $paymentID = $this->Request()->getParam('id', '');


            $this->notifications->onNotify($transactionID, $paymentID);

            $data = [
                'success' => true,
                'message' => 'The payment status for the order has been processed.'
            ];

            echo json_encode($data, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error in Mollie Notification',
                [
                    'error' => $e->getMessage(),
                ]
            );

            $data = [
                'success' => false,
                'message' => 'There was a problem. Please see the logs for more.'
            ];

            http_response_code(500);
            echo json_encode($data);
        }

        die();
    }

    /**
     * Route to retry making an order
     *
     * @throws Exception
     */
    public function retryAction()
    {
        try {
            $this->loadServices();

            $orderNumber = $this->Request()->getParam('orderNumber');

            /** @var Order|null $order */
            $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

            if ($order instanceof Order) {
                $this->orderCancellation->cancelAndRestoreByOrder($order);
            }
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error in retry action',
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        $this->redirectToShopwareCheckoutFailed($this);
    }

    /**
     * Get the issuers for the iDEAL payment method.
     * Called in an ajax call on the frontend.
     */
    public function idealIssuersAction()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        try {

            $this->loadServices();

            $customer = $this->customers->getCurrent();

            if (!$customer instanceof Customer) {
                throw new \Exception('No active customer found for iDEAL AJAX list');
            }

            /** @var array $idealIssuers */
            $idealIssuers = $this->iDEAL->getIssuers($customer);

            $this->sendResponse([
                'data' => $idealIssuers,
                'success' => true,
            ]);

        } catch (\Exception $ex) {

            $this->logger->error(
                'Error iDEAL issuer AJAX action.',
                [
                    'error' => $ex->getMessage(),
                ]
            );

            $this->sendResponse(
                [
                    'message' => $ex->getMessage(),
                    'success' => false
                ],
                500
            );
        }
    }

    /**
     *
     */
    public function componentsAction()
    {
        try {
            $this->loadServices();

            /** @var MollieApiFactory $apiFactory */
            $apiFactory = Shopware()->Container()->get('mollie_shopware.api_factory');
            $mollieAPI = $apiFactory->create();

            /** @var Profile $mollieProfile */
            $mollieProfile = $mollieAPI->profiles->get('me');

            $mollieProfileId = '';

            if ($mollieProfile !== null) {
                $mollieProfileId = $mollieProfile->id;
            }

            $mollieTestMode = $this->config->isTestmodeActive();


            header('Content-Type: text/javascript');

            $script = file_get_contents(__DIR__ . '/../../Resources/views/frontend/_public/src/js/components.js');
            $script = str_replace('[mollie_profile_id]', $mollieProfileId, $script);
            $script = str_replace('[mollie_locale]', $this->localeFinder->getPaymentLocale(Shopware()->Shop()->getLocale()->getLocale()), $script);
            $script = str_replace('[mollie_testmode]', ($mollieTestMode === true) ? 'true' : 'false', $script);
            echo $script;

            die();
        } catch (\Exception $ex) {
            $this->logger->error('Error when showing Credit Card Components: ' . $ex->getMessage());

            $this->sendResponse(
                [
                    'message' => 'There was an error. Please see the logs.',
                    'success' => false
                ],
                500
            );
        }
    }


    /**
     * Gets the current user id if
     * a user and data can be found.
     *
     * @return int
     */
    private function getBasketUserId()
    {
        $user = $this->getUser();

        if ($user === null) {
            return 0;
        }

        if (!isset($user['additional'])) {
            return 0;
        }

        if (!isset($user['additional']['user'])) {
            return 0;
        }

        if (!isset($user['additional']['user']['id'])) {
            return 0;
        }

        return $user['additional']['user']['id'];
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

        try {
            $applePayFactory = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.factory');
            $this->applePay = $applePayFactory->createHandler();

            $creditCardService = Shopware()->Container()->get('mollie_shopware.credit_card_service');

            $config = Shopware()->Container()->get('mollie_shopware.config');
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');
            $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

            $this->iDEAL = $this->container->get('mollie_shopware.components.ideal');
            $this->customers = $this->container->get('mollie_shopware.customer');


            $this->config = $config;
            $this->orderService = $orderService;
            $repoTransactions = $this->getTransactionRepository();

            $this->localeFinder = new LocaleFinder();

            /** @var Shipping $shipping */
            $shipping = Shopware()->Container()->get('mollie_shopware.components.shipping');

            /** @var Basket $basket */
            $basket = Shopware()->Container()->get('mollie_shopware.components.basket');

            /** @var MollieGatewayInterface $mollieGateway */
            $mollieGateway = Shopware()->Container()->get('mollie_shopware.gateways.mollie');

            $entityManager = Shopware()->Container()->get('models');

            $sOrder = Shopware()->Modules()->Order();
            $sBasket = Shopware()->Modules()->Basket();

            $orderUpdater = Shopware()->Container()->get('mollie_shopware.components.order.order_updater');

            $sessionManager = Shopware()->Container()->get('mollie_shopware.components.session_manager');

            $paymentStatusResolver = Shopware()->Container()->get('mollie_shopware.components.transaction.payment_status_resolver');

            $paymentConfigResolver = Shopware()->Container()->get('mollie_shopware.components.config.payments');

            $confirmationMail = new ConfirmationMail($sOrder, $repoTransactions);

            $tokeAnonymizer = new TokenAnonymizer(
                '*',
                self::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT,
                self::TOKEN_ANONYMIZER_MAX_LENGTH
            );

            $this->orderCancellation = Shopware()->Container()->get('mollie_shopware.components.order.cancellation');


            $swOrderUpdater = new ShopwareOrderUpdater($this->config, $entityManager);

            $swOrderBuilder = new ShopwareOrderBuilder(
                $this,
                $orderService,
                $this->logger
            );

            $statusConverter = new MollieStatusConverter(
                $paymentService,
                new MollieRefundStatus()
            );


            # get the configuration from the Shopware Backend about
            # the rounding after the tax.
            # This makes a huge difference for net-based shops where we have to calculate
            # the gross price for Mollie.
            $roundAfterTax = Shopware()->Config()->offsetGet('roundNetAfterTax');

            $transactionBuilder = new TransactionBuilder(
                $sessionManager,
                $repoTransactions,
                $basket,
                $shipping,
                (bool)$roundAfterTax
            );


            $this->checkout = new CheckoutSessionFacade(
                $paymentService,
                $orderService,
                $this->logger,
                $this,
                $entityManager,
                $this->localeFinder,
                $sBasket,
                $swOrderBuilder,
                $tokeAnonymizer,
                $this->applePay,
                $creditCardService,
                $repoTransactions,
                $sessionManager,
                $transactionBuilder,
                $paymentConfigResolver
            );

            $this->checkoutReturn = new FinishCheckoutFacade(
                $config,
                $orderService,
                $paymentService,
                $repoTransactions,
                $this->logger,
                $mollieGateway,
                new MollieStatusValidator(),
                $swOrderUpdater,
                $swOrderBuilder,
                $statusConverter,
                $orderUpdater,
                $confirmationMail,
                $paymentConfigResolver
            );

            $this->notifications = new MollieShopware\Facades\Notifications\Notifications(
                $this->logger,
                $this->config,
                $repoTransactions,
                $this->orderService,
                $orderUpdater,
                $this->orderCancellation,
                $sessionManager,
                $paymentStatusResolver,
                $paymentConfigResolver
            );

            $this->restoreSessionFacade = new RestoreSessionFacade(
                $repoTransactions,
                $sessionManager,
                $this->logger
            );

        } catch (\Exception $ex) {
            $this->logger->emergency('Fatal Problem when preparing services! ' . $ex->getMessage());

            throw $ex;
        }
    }
}
