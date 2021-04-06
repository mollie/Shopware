<?php

namespace MollieShopware\Facades\CheckoutSession;

use MollieShopware\Components\ApplePayDirect\Handler\ApplePayDirectHandler;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Config;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Components\Helpers\LocaleFinder;
use MollieShopware\Components\Order\ShopwareOrderBuilder;
use MollieShopware\Components\Services\CreditCardService;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Components\SessionSnapshot\SessionSnapshotManager;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\SessionSnapshot\Repository;
use MollieShopware\Models\SessionSnapshot\SessionSnapshot;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use MollieShopware\Services\TokenAnonymizer\TokenAnonymizer;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
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
     * @var Basket
     */
    private $basket;

    /**
     * @var Shipping
     */
    private $shipping;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var Repository
     */
    private $repoSessionSnapshots;

    /**
     * @var LocaleFinder
     */
    private $localeFinder;

    /**
     * @var Order|null
     */
    private $restorableOrder;

    /**
     * @var $sBasket
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
     * @var SessionSnapshotManager
     */
    private $sessionSnapshotManager;


    /**
     * @param Config $config
     * @param PaymentService $paymentService
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @param Shopware_Controllers_Frontend_Payment $controller
     * @param ModelManager $modelManager
     * @param Basket $basket
     * @param Shipping $shipping
     * @param TransactionRepository $repoTransactions
     * @param Repository $repoSessionSnapshots
     * @param LocaleFinder $localeFinder
     * @param $sBasket
     * @param ShopwareOrderBuilder $swOrderBuilder
     * @param TokenAnonymizer $anonymizer
     * @param ApplePayDirectHandler $applePay
     * @param CreditCardService $creditCard
     */
    public function __construct(Config $config, PaymentService $paymentService, OrderService $orderService, LoggerInterface $logger, Shopware_Controllers_Frontend_Payment $controller, ModelManager $modelManager, Basket $basket, Shipping $shipping, TransactionRepository $repoTransactions, Repository $repoSessionSnapshots, LocaleFinder $localeFinder, $sBasket, ShopwareOrderBuilder $swOrderBuilder, TokenAnonymizer $anonymizer, ApplePayDirectHandler $applePay, CreditCardService $creditCard)
    {
        $this->config = $config;
        $this->paymentService = $paymentService;
        $this->orderService = $orderService;
        $this->logger = $logger;
        $this->controller = $controller;
        $this->modelManager = $modelManager;
        $this->basket = $basket;
        $this->shipping = $shipping;
        $this->repoTransactions = $repoTransactions;
        $this->repoSessionSnapshots = $repoSessionSnapshots;
        $this->localeFinder = $localeFinder;
        $this->sBasket = $sBasket;
        $this->swOrderBuilder = $swOrderBuilder;
        $this->tokenAnonymizer = $anonymizer;
        $this->applePay = $applePay;
        $this->creditCardService = $creditCard;

        $this->sessionSnapshotManager = Shopware()->Container()->get('mollie_shopware.components.session_snapshot.manager');
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
     * @return CheckoutSession
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Enlight_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startCheckoutSession($basketUserId, $paymentShortName, $basketSignature, $currencyShortName)
    {
        # immediately reset our previous order
        # just to make sure we don't have a previous one accidentally
        $this->restorableOrder = null;

        $basketData = $this->sBasket->sGetBasketData();

        # we want to log anonymized tokens
        # to see if they are used correctly.
        $tokenCreditCard = $this->creditCardService->getCardToken();
        $tokenApplePay = $this->applePay->getPaymentToken();

        $this->logger->info(
            'Starting checkout for user: ' . $basketUserId . ' with payment: ' . $paymentShortName,
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

        if (!$this->sBasket->sCountBasket()) {
            throw new \Exception('No items in basket');
        }


        # build and create our transaction
        # this is the most important line that helps us
        # to get the bridge between the mollie payments and the shopware orders.
        # it contains all necessary things for upcoming workflows.
        $transaction = $this->buildTransaction($basketSignature, $currencyShortName);
        $this->modelManager->persist($transaction);
        $this->modelManager->flush();


        # we need this variable for our payment request
        # so its either empty or filled
        $sessionSnapshotHash = '';


        # if we create the order AFTER the payment
        # we need to make sure to backup a snapshot of the session variables.
        # if a browser switch or anything like that happens, the session might be gone
        # and thus the order can NOT be saved...so we would need to restore the session
        if (!$this->config->createOrderBeforePayment()) {
            $sessionSnapshot = $this->sessionSnapshotManager->buildSnapshot($transaction->getId());
            $this->repoSessionSnapshots->save($sessionSnapshot);

            $sessionSnapshotHash = $sessionSnapshot->getHash();
        }


        if ($this->config->createOrderBeforePayment()) {

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
            $sessionSnapshotHash
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
        $transactionId = $this->repoTransactions->getLastId() + 1;

        $transaction = new Transaction();
        $transaction->setId($transactionId);
        $transaction->setTransactionId('mollie_' . $transactionId);
        $transaction->setSessionId(\Enlight_Components_Session::getId());
        $transaction->setShopId(Shopware()->Shop()->getId());
        $transaction->setBasketSignature($basketSignature);
        $transaction->setLocale($this->localeFinder->getPaymentLocale());
        $transaction->setCurrency($currency);
        $transaction->setTotalAmount($this->controller->getAmount());

        # now save our transaction immediately
        # i dont know if some code below needs it from the DB ;)
        $this->repoTransactions->save($transaction);


        $currentCustomerClass = new CurrentCustomer(Shopware()->Session(), Shopware()->Models());
        $customer = $currentCustomerClass->getCurrent();

        if ($customer instanceof Customer) {
            $transaction->setCustomer($customer);
            $transaction->setCustomerId($customer->getId());
        }


        // set transaction as tax free
        if (isset($this->controller->getUser()['additional']) && (!isset($this->controller->getUser()['additional']['charge_vat']) || empty($this->controller->getUser()['additional']['charge_vat']))) {
            $transaction->setTaxFree(true);
        }

        # set transaction as net order
        # e.g. show_net = false means its a NET order
        if (isset($this->controller->getUser()['additional']) && (!isset($this->controller->getUser()['additional']['show_net']) || empty($this->controller->getUser()['additional']['show_net']))) {
            $transaction->setNet(true);
        }


        # build our tax mode depending on the configuration from above
        $taxMode = new TaxMode(!$transaction->getTaxFree(), $transaction->getNet());
        $transactionBuilder = new TransactionItemBuilder($taxMode);


        $transactionItems = new \Doctrine\Common\Collections\ArrayCollection();

        /** @var BasketItem[] $basketLines */
        $basketLines = $this->basket->getBasketLines($this->controller->getUser());

        /** @var BasketItem $basketItem */
        foreach ($basketLines as $basketItem) {
            # build our new transaction item from the basket line.
            # this must be perfectly rounded!
            $transactionItem = $transactionBuilder->buildTransactionItem($transaction, $basketItem);
            $transactionItems->add($transactionItem);
        }


        /** @var BasketItem $shippingItem */
        $shippingItem = $this->shipping->getCartShippingCosts();

        # if we have shipping costs
        # then convert them to a transaction item too
        if ($shippingItem->getUnitPrice() > 0) {

            # our articles are all correctly set to gross or net
            # price depending on the shop setting.
            # but shipping will always return gross AND net in different fields.
            # our transaction builder will automatically convert NET to GROSS
            # so we need to make sure to set the net price manually in that case.
            $shippingItem->setNetMode($taxMode->isNetOrder());

            $transactionItem = $transactionBuilder->buildTransactionItem($transaction, $shippingItem);
            $transactionItems->add($transactionItem);
        }

        $transaction->setItems($transactionItems);


        return $transaction;
    }
}
