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
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use MollieShopware\Services\TokenAnonymizer\TokenAnonymizer;
use Psr\Log\LoggerInterface;
use Shopware\Components\DependencyInjection\Bridge\Session;
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
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;


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
     */
    public function __construct(Config $config, PaymentService $paymentService, OrderService $orderService, LoggerInterface $logger, Shopware_Controllers_Frontend_Payment $controller, ModelManager $modelManager, Basket $basket, Shipping $shipping, LocaleFinder $localeFinder, $sBasket, ShopwareOrderBuilder $swOrderBuilder, TokenAnonymizer $anonymizer, ApplePayDirectHandler $applePay, CreditCardService $creditCard, TransactionRepository $repoTransactions, SessionManager $sessionManager)
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
            $paymentToken
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


        $transactionItems = new \Doctrine\Common\Collections\ArrayCollection();

        $articlePricesAreNet = $transaction->getNet();


        # build our tax mode depending on the configuration from above
        $taxMode = new TaxMode(!$transaction->getTaxFree());
        $transactionBuilder = new TransactionItemBuilder($taxMode);


        /** @var BasketItem[] $basketLines */
        $basketLines = $this->basket->getBasketLines($this->controller->getUser());

        foreach ($basketLines as $basketItem) {

            # find out if our article price is gross or net.
            # we set that information for the line item.
            $basketItem->setIsGrossPrice(!$articlePricesAreNet);

            $transactionItem = $transactionBuilder->buildTransactionItem($transaction, $basketItem);
            $transactionItems->add($transactionItem);
        }


        /** @var BasketItem $shippingItem */
        $shippingItem = $this->shipping->getCartShippingCosts();

        if ($shippingItem->getUnitPrice() > 0) {

            # if we have a shipping price of 7.99, Shopware would
            # create 6.71 as net price from it. If we would calculate it
            # back to gross, we would end up with 7.98.
            # thus we always have to make sure, we use the (correct) gross price
            # when building our transaction item.
            $shippingItem->setIsGrossPrice(true);

            $transactionItem = $transactionBuilder->buildTransactionItem($transaction, $shippingItem);
            $transactionItems->add($transactionItem);
        }

        $transaction->setItems($transactionItems);

        return $transaction;
    }
}
