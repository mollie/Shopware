<?php

namespace MollieShopware\Facades\CheckoutSession;

use MollieShopware\Components\ApplePayDirect\Handler\ApplePayDirectHandler;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Basket\BasketInterface;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Components\Helpers\LocaleFinder;
use MollieShopware\Components\Order\ShopwareOrderBuilder;
use MollieShopware\Components\Services\CreditCardService;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Components\Shipping\ShippingInterface;
use MollieShopware\Components\TransactionBuilder\TransactionBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use MollieShopware\Services\TokenAnonymizer\TokenAnonymizer;
use Psr\Log\LoggerInterface;
use sBasket;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware_Controllers_Frontend_Payment;

class CheckoutSessionFacade
{

    /**
     * @var Config
     */
    private $config;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Shopware_Controllers_Frontend_Payment
     */
    private $controller;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var BasketInterface
     */
    private $basket;

    /**
     * @var ShippingInterface
     */
    private $shipping;

    /**
     * @var LocaleFinder
     */
    private $localeFinder;

    /**
     * @var Order|null
     */
    private $restorableOrder;

    /**
     * @var sBasket
     */
    private $sBasket;

    /**
     * @var ShopwareOrderBuilder
     */
    private $swOrderBuilder;

    /**
     * @var TokenAnonymizer
     */
    private $tokenAnonymizer;

    /**
     * @var ApplePayDirectHandler
     */
    private $applePay;

    /**
     * @var CreditCardService
     */
    private $creditCardService;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var Config\PaymentConfigResolver
     */
    private $paymentConfig;


    /**
     * CheckoutSessionFacade constructor.
     * @param Config $config
     * @param PaymentService $paymentService
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @param Shopware_Controllers_Frontend_Payment $controller
     * @param ModelManager $modelManager
     * @param Basket $basket
     * @param Shipping $shipping
     * @param LocaleFinder $localeFinder
     * @param $sBasket
     * @param ShopwareOrderBuilder $swOrderBuilder
     * @param TokenAnonymizer $anonymizer
     * @param ApplePayDirectHandler $applePay
     * @param CreditCardService $creditCard
     * @param TransactionRepository $repoTransactions
     * @param SessionManager $sessionManager
     * @param TransactionBuilder $transactionBuilder
     * @param Config\PaymentConfigResolver $paymentConfig
     */
    public function __construct(Config $config, PaymentService $paymentService, OrderService $orderService, LoggerInterface $logger, Shopware_Controllers_Frontend_Payment $controller, ModelManager $modelManager, Basket $basket, Shipping $shipping, LocaleFinder $localeFinder, $sBasket, ShopwareOrderBuilder $swOrderBuilder, TokenAnonymizer $anonymizer, ApplePayDirectHandler $applePay, CreditCardService $creditCard, TransactionRepository $repoTransactions, SessionManager $sessionManager, TransactionBuilder $transactionBuilder, Config\PaymentConfigResolver $paymentConfig)
    {
        $this->config = $config;
        $this->paymentService = $paymentService;
        $this->orderService = $orderService;
        $this->logger = $logger;
        $this->controller = $controller;
        $this->modelManager = $modelManager;
        $this->basket = $basket;
        $this->shipping = $shipping;
        $this->localeFinder = $localeFinder;
        $this->sBasket = $sBasket;
        $this->swOrderBuilder = $swOrderBuilder;
        $this->tokenAnonymizer = $anonymizer;
        $this->applePay = $applePay;
        $this->creditCardService = $creditCard;
        $this->repoTransactions = $repoTransactions;
        $this->sessionManager = $sessionManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->paymentConfig = $paymentConfig;
    }


    /**
     * @return Order|null
     */
    public function getRestorableOrder()
    {
        return $this->restorableOrder;
    }


    /**
     * @param $basketUserId
     * @param $paymentShortName
     * @param $basketSignature
     * @param $currencyShortName
     * @param $shopId
     * @param $billingAddressID
     * @param $shippingAddressID
     * @return CheckoutSession
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \MollieShopware\Exceptions\MolliePaymentConfigurationNotFound
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startCheckoutSession($basketUserId, $paymentShortName, $basketSignature, $currencyShortName, $shopId, $billingAddressID, $shippingAddressID)
    {
        # immediately reset our previous order
        # just to make sure we don't have a previous one accidentally
        $this->restorableOrder = null;

        $basketData = $this->sBasket->sGetBasketData();

        if (!$this->sBasket->sCountBasket()) {
            throw new \Exception('No items in basket');
        }

        # we want to log anonymized tokens
        # to see if they are used correctly.
        $tokenCreditCard = $this->creditCardService->getCardToken();
        $tokenApplePay = $this->applePay->getPaymentToken();


        # build and create our transaction
        # this is the most important line that helps us
        # to get the bridge between the mollie payments and the shopware orders.
        # it contains all necessary things for upcoming workflows.
        $transaction = $this->buildTransaction($basketSignature, $currencyShortName);
        $this->modelManager->persist($transaction);
        $this->modelManager->flush();


        $this->logger->info(
            'Starting checkout for Transaction: ' . $transaction->getId() . ' with payment: ' . $paymentShortName,
            [
                'basket' => [
                    'amount' => $basketData['Amount'],
                    'quantity' => $basketData['Quantity'],
                    'payment' => $paymentShortName,
                    'user' => $basketUserId
                ],
                'tokens' => [
                    'creditcard' => $this->tokenAnonymizer->anonymize($tokenCreditCard),
                    'applepay' => $this->tokenAnonymizer->anonymize($tokenApplePay),
                ]
            ]
        );

        # to avoid problems on lost sessions, we have to ensure
        # that we can restore a session.
        # thus we create a payment token, that can be used for this in the returnAction
        $paymentToken = $this->sessionManager->generateSessionToken($transaction);

        # now check, if we should create the order
        # before or after the payment
        $orderCreation = $this->paymentConfig->getFinalOrderCreation($paymentShortName, $shopId);

        if ($orderCreation === OrderCreationType::BEFORE_PAYMENT) {

            # create the order in our system.
            # attention, this is the point where the basket is officially "empty"
            # because the order is completed in Shopware
            $orderNumber = $this->swOrderBuilder->createOrderBeforePayment(
                $transaction->getTransactionId(),
                $basketSignature
            );

            $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

            if (!$order instanceof Order) {
                throw new \Exception('The order with order number ' . $orderNumber . ' could not be found');
            }

            # now that we have the order, we need to remember it
            # this is required to restore the basket from that order in case of
            # any additional failures below. otherwise the cart is empty because
            # the order is already completed.
            $this->restorableOrder = $order;

            # now update all required references of our transaction
            $transaction->setOrderId($order->getId());
            $transaction->setOrderNumber($orderNumber);

            $this->repoTransactions->save($transaction);
        }

        # now start the actual Mollie order.
        # we prepare the request and send it to Mollie.
        # the response will create an URL that we need to redirect
        # the user to for further payment steps.
        $checkoutUrl = $this->paymentService->startMollieSession(
            $paymentShortName,
            $transaction,
            $paymentToken,
            $billingAddressID,
            $shippingAddressID
        );

        # some payment methods are approved and
        # paid immediately and don't require a redirect.
        # so we just grab this information using our constant
        $redirectRequired = ($checkoutUrl !== PaymentService::CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED);

        return new CheckoutSession(
            $redirectRequired,
            $transaction,
            $checkoutUrl
        );
    }

    /**
     * @param $basketSignature
     * @param $currency
     * @return Transaction
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function buildTransaction($basketSignature, $currency)
    {
        $currentCustomerClass = new CurrentCustomer(Shopware()->Session(), Shopware()->Models());
        $customer = $currentCustomerClass->getCurrent();

        $locale = $this->localeFinder->getPaymentLocale(Shopware()->Shop()->getLocale()->getLocale());

        $isTaxFree = false;
        $isNet = false;


        if (isset($this->controller->getUser()['additional']) && (!isset($this->controller->getUser()['additional']['charge_vat']) || empty($this->controller->getUser()['additional']['charge_vat']))) {
            $isTaxFree = true;
        }

        # set transaction as net order
        # e.g. show_net = false means its a NET order
        if (isset($this->controller->getUser()['additional']) && (!isset($this->controller->getUser()['additional']['show_net']) || empty($this->controller->getUser()['additional']['show_net']))) {
            $isNet = true;
        }

        $shopId = Shopware()->Shop()->getId();

        $transaction = $this->transactionBuilder->buildTransaction(
            $basketSignature,
            $currency,
            $this->controller->getAmount(),
            $shopId,
            $this->controller->getUser(),
            $locale,
            $customer,
            $isTaxFree,
            $isNet
        );

        $this->repoTransactions->save($transaction);

        return $transaction;
    }
}
