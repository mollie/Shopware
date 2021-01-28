<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Profile;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectFactory;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\Base\AbstractPaymentController;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Config;
use MollieShopware\Components\Helpers\LocaleFinder;
use MollieShopware\Components\Helpers\MollieRefundStatus;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\Order\OrderCancellation;
use MollieShopware\Components\Order\OrderUpdater;
use MollieShopware\Components\Order\ShopwareOrderBuilder;
use MollieShopware\Components\Services\IdealService;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Facades\CheckoutSession\CheckoutSessionFacade;
use MollieShopware\Facades\FinishCheckout\FinishCheckoutFacade;
use MollieShopware\Facades\FinishCheckout\Services\ConfirmationMail;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Facades\FinishCheckout\Services\ShopwareOrderUpdater;
use MollieShopware\Facades\Notifications\Notifications;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Services\TokenAnonymizer\TokenAnonymizer;
use MollieShopware\Traits\MollieApiClientTrait;
use Shopware\Models\Order\Order;

class Shopware_Controllers_Frontend_Mollie extends AbstractPaymentController
{
    use MollieApiClientTrait;


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
     * @throws ApiException
     */
    public function directAction()
    {
        try {

            $this->loadServices();

            # persist the basket in the database
            # by saving it from the session
            $signature = $this->doPersistBasket();

            $currency = method_exists($this, 'getCurrencyShortName') ? $this->getCurrencyShortName() : 'EUR';

            # create a new checkout session
            # by preparing transactions, orders and more
            $session = $this->checkout->startCheckoutSession(
                $this->getBasketUserId(),
                $this->getPaymentShortName(),
                $signature,
                $currency
            );

            # some payment methods do not require a redirect to mollie.
            # these are automatically approved and thus we
            # have to immediately redirect to the return action.
            if (!$session->isRedirectToMollieRequired()) {
                $this->redirect(
                    [
                        'controller' => 'Mollie',
                        'action' => 'return',
                        'transactionNumber' => $session->getTransaction()->getId(),
                    ]
                );
                return;
            }

            $this->redirect($session->getCheckoutUrl());

        } catch (Throwable $ex) {

            $this->logger->error(
                'Error when starting Mollie checkout',
                array(
                    'error' => $ex->getMessage()
                )
            );

            # in theory this is not catched,
            # but for a better code understanding, we keep it here
            if ($this->checkout->getRestorableOrder() instanceof Order) {
                $this->orderCancellation->cancelAndRestoreByOrder($this->checkout->getRestorableOrder());
            }

            $this->redirectBack(self::ERROR_PAYMENT_FAILED);

        } finally {

            # we always have to immediately clear the token in SUCCESS or FAILURE ways
            $this->applePay->setPaymentToken('');
        }
    }

    /**
     * Returns the user from Mollie's checkout and processes his payment.
     * If payment failed we restore the basket.
     * If payment succeeded we show the /checkout/finish page
     *
     * @throws Exception
     */
    public function returnAction()
    {
        try {

            $this->loadServices();

            /** @var string $transactionNumber */
            $transactionNumber = $this->Request()->getParam('transactionNumber');

            if (empty($transactionNumber)) {
                throw new Exception('Missing Transaction Number');
            }

            $this->logger->debug('User is returning from Mollie for Transaction ' . $transactionNumber);

            $checkoutData = $this->checkoutReturn->finishTransaction($transactionNumber);

            $this->logger->info('Finished checkout for Transaction ' . $transactionNumber);


            # prepare the view data (i dont know why)
            $this->view->assign('orderNumber', $checkoutData->getOrdernumber());

            # make sure to finish the order with
            # the original shopware way
            $this->redirectToFinish($checkoutData->getTemporaryId());

        } catch (Throwable $ex) {

            $this->logger->error(
                'Checkout failed when returning to shop!',
                array(
                    'error' => $ex->getMessage(),
                )
            );

            # if we have a transaction number
            # then make sure to cancel the order of the transaction if existing
            if (!empty($transactionNumber)) {
                $this->orderCancellation->cancelAndRestoreByTransaction($transactionNumber);
            }

            $this->redirectBack(self::ERROR_PAYMENT_FAILED);

        } finally {
            if (!empty($transactionNumber)) {
                $this->checkoutReturn->cleanupTransaction($transactionNumber);
            }
        }
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

            $data = array(
                'success' => true,
                'message' => 'The payment status for the order has been processed.'
            );

            echo json_encode($data, JSON_PRETTY_PRINT);

        } catch (\Throwable $e) {

            $this->logger->error(
                'Error in Mollie Notification',
                array(
                    'error' => $e->getMessage(),
                )
            );

            $data = array(
                'success' => false,
                'message' => 'There was a problem. Please see the logs for more.'
            );

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
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        $this->redirectBack();
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

            // get the issuers from the IdealService, or return an error
            /** @var IdealService $ideal */
            $idealService = $this->container->get('mollie_shopware.ideal_service');

            /** @var array $idealIssuers */
            $idealIssuers = $idealService->getIssuers();

            $this->sendResponse([
                'data' => $idealIssuers,
                'success' => true,
            ]);

        } catch (Throwable $ex) {

            $this->sendResponse([
                'message' => $ex->getMessage(),
                'success' => false],
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

            /** @var Profile $mollieProfile */
            $mollieProfile = $this->getMollieApi()->profiles->get('me');

            $mollieProfileId = '';

            if ($mollieProfile !== null) {
                $mollieProfileId = $mollieProfile->id;
            }

            $mollieTestMode = $this->config->isTestmodeActive();


            header('Content-Type: text/javascript');

            $script = file_get_contents(__DIR__ . '/../../Resources/views/frontend/_public/src/js/components.js');
            $script = str_replace('[mollie_profile_id]', $mollieProfileId, $script);
            $script = str_replace('[mollie_locale]', $this->localeFinder->getPaymentLocale(), $script);
            $script = str_replace('[mollie_testmode]', ($mollieTestMode === true) ? 'true' : 'false', $script);
            echo $script;

            die();

        } catch (Throwable $ex) {

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


            $basketService = Shopware()->Container()->get('mollie_shopware.basket_service');

            $config = Shopware()->Container()->get('mollie_shopware.config');
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');
            $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

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

            $orderUpdater = new OrderUpdater($this->config, $sOrder);
            $confirmationMail = new ConfirmationMail($sOrder, $repoTransactions);

            $tokeAnonymizer = new TokenAnonymizer(
                '*',
                self::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT,
                self::TOKEN_ANONYMIZER_MAX_LENGTH
            );


            $this->orderCancellation = new OrderCancellation(
                $config,
                $repoTransactions,
                $orderService,
                $basketService,
                $paymentService,
                $orderUpdater
            );

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

            $this->checkout = new CheckoutSessionFacade(
                $config,
                $paymentService,
                $orderService,
                $this->logger,
                $this,
                $entityManager,
                $basket,
                $shipping,
                $repoTransactions,
                $this->localeFinder,
                $sBasket,
                $swOrderBuilder,
                $tokeAnonymizer,
                $this->applePay,
                $creditCardService
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
                $confirmationMail
            );

            $this->notifications = new MollieShopware\Facades\Notifications\Notifications(
                $this->logger,
                $this->config,
                $repoTransactions,
                $this->orderService,
                $paymentService,
                $orderUpdater,
                $statusConverter,
                $this->orderCancellation
            );

        } catch (Throwable $ex) {

            $this->logger->emergency('Fatal Problem when preparing services! ' . $ex->getMessage());

            throw $ex;
        }
    }


}
