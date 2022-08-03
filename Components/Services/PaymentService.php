<?php

namespace MollieShopware\Components\Services;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectFactory;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Components\iDEAL\iDEALInterface;
use MollieShopware\Components\Mollie\Builder\MolliePaymentBuilder;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Components\Mollie\Services\TransactionUUID\TransactionUUID;
use MollieShopware\Components\Mollie\Services\TransactionUUID\UnixTimestampGenerator;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Translation\Translation;
use MollieShopware\Exceptions\MollieOrderNotFound;
use MollieShopware\Exceptions\MolliePaymentConfigurationNotFound;
use MollieShopware\Exceptions\MolliePaymentNotFound;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Gateways\Mollie\Exceptions\InvalidOrderAmountException;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\OrderLines;
use MollieShopware\Models\Payment\Configuration;
use MollieShopware\Models\Payment\ConfigurationKeys;
use MollieShopware\Models\Payment\Repository;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use MollieShopware\MollieShopware;
use MollieShopware\Services\Mollie\Payments\PaymentFactory;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;
use MollieShopware\Services\Mollie\Payments\Requests\ApplePay;
use MollieShopware\Services\Mollie\Payments\Requests\BankTransfer;
use MollieShopware\Services\Mollie\Payments\Requests\CreditCard;
use MollieShopware\Services\Mollie\Payments\Requests\IDeal;
use MollieShopware\Services\MollieOrderRequestAnonymizer\MollieOrderRequestAnonymizer;
use Psr\Log\LoggerInterface;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;

class PaymentService
{

    /**
     * yes this is a small hack :)
     * some credit cards to not create a checkoutURL.
     * This is in the case of approved apple pay payments,
     * and also credit cards without a 3d secure (isnt allowed except on test systems)
     * in that case the payment is immediately PAID and thus we "just" redirect to the finish page.
     */
    const CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED = 'OK_NO_CHECKOUT_REDIRECT_REQUIRED';


    /**
     * @var MollieApiFactory
     */
    protected $apiFactory;

    /**
     * @var \Mollie\Api\MollieApiClient
     */
    protected $apiClient;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Config\PaymentConfigResolver
     */
    private $paymentConfig;

    /**
     * @var array
     */
    protected $customEnvironmentVariables;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var PaymentFactory
     */
    private $paymentFactory;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var \Shopware\Models\Order\Repository
     */
    private $orderRepo;

    /**
     * @var CreditCardService $creditCardService
     */
    private $creditCardService;

    /**
     * @var ApplePayDirectFactory $applePayFactory
     */
    private $applePayFactory;

    /**
     * @var iDEALInterface $idealService
     */
    private $idealService;

    /**
     * @var CurrentCustomer
     */
    private $customer;

    /**
     * @var EntityRepository
     */
    private $repoAddress;

    /**
     * @var \Enlight_Template_Manager
     */
    private $smarty;

    /**
     * @var EntityRepository|\MollieShopware\Models\OrderLinesRepository
     */
    private $orderLinesRepo;



    /**
     * @param MollieApiFactory $apiFactory
     * @param Config $config
     * @param Config\PaymentConfigResolver $paymentConfig
     * @param MollieGatewayInterface $gwMollie
     * @param LoggerInterface $logger
     * @param array $customEnvironmentVariables
     * @throws ApiException
     */
    public function __construct(MollieApiFactory $apiFactory, Config $config, Config\PaymentConfigResolver $paymentConfig, MollieGatewayInterface $gwMollie, array $customEnvironmentVariables)
    {
        $this->apiFactory = $apiFactory;
        $this->apiClient = $apiFactory->create();
        $this->config = $config;
        $this->paymentConfig = $paymentConfig;
        $this->gwMollie = $gwMollie;

        $this->customEnvironmentVariables = $customEnvironmentVariables;

        $this->orderLinesRepo = Shopware()->Container()->get('models')->getRepository('\MollieShopware\Models\OrderLines');
        $this->repoTransactions = Shopware()->Container()->get('models')->getRepository('\MollieShopware\Models\Transaction');
        $this->orderRepo = Shopware()->Models()->getRepository(Order::class);
        $this->repoAddress = Shopware()->Models()->getRepository(Address::class);

        $this->smarty = Shopware()->Container()->get('template');

        $this->paymentFactory = new PaymentFactory();
    }

    /**
     * This function helps to use a different api client
     * for this payment methods service.
     * One day there should be a refactoring to do this in the constructor.
     *
     * @param MollieApiClient $client
     */
    public function switchApiClient(MollieApiClient $client)
    {
        $this->gwMollie->switchClient($client);
    }

    /**
     * This function helps to use a different config
     * for this payment methods service.
     * One day there should be a refactoring to do this in the constructor.
     *
     * @param Config $config
     */
    public function switchConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param Order $order
     * @throws ApiException
     */
    public function setApiKeyForSubShop(Order $order)
    {
        // Use the order's shop in the in the config service
        $this->config->setShop($order->getShop()->getId());

        $this->apiClient = $this->apiFactory->create($order->getShop()->getId());
    }

    /**
     * @param $paymentMethodName
     * @param Transaction $transaction
     * @param $paymentToken
     * @param $billingAddressID
     * @param $shippingAddressID
     * @throws ApiException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @return null|string
     */
    public function startMollieSession($paymentMethodName, Transaction $transaction, $paymentToken, $billingAddressID, $shippingAddressID)
    {
        # ATTENTION
        # we have to do this here now,
        # otherwise the shipping command in the backend won't work because somehow
        # a shop service is somewhere used in there...
        $this->creditCardService = Shopware()->Container()->get('mollie_shopware.credit_card_service');
        $this->applePayFactory = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.factory');
        $this->idealService = Shopware()->Container()->get('mollie_shopware.ideal_service');
        $this->customer = Shopware()->Container()->get('mollie_shopware.customer');
        $shopID = Shopware()->Shop()->getId();

        $shopwareOrder = null;

        # check if we have an existing order in shopware for our transaction
        # if so load it and use it as meta data information
        if (!empty($transaction->getOrderId())) {
            $shopwareOrder = $this->orderRepo->find($transaction->getOrderId());

            $transaction->setOrderId($shopwareOrder->getId());
        }

        # convert our mollie_xyz to "xyz" only
        $cleanPaymentMethod = str_replace(MollieShopware::PAYMENT_PREFIX, '', $paymentMethodName);

        # ------------------------------------------------------------------------------------------------------

        /** @var null|PaymentInterface $paymentMethodObject */
        $paymentMethodObject = $this->paymentFactory->createByPaymentName($cleanPaymentMethod);

        if ($paymentMethodObject === null) {
            throw new \Exception('Payment Request for payment: ' . $cleanPaymentMethod . ' not implemented yet!');
        }

        # ------------------------------------------------------------------------------------------------------
        # BUILD OUR PAYMENT DATA

        $paymentBuilder = new MolliePaymentBuilder(
            new TransactionUUID(new UnixTimestampGenerator()),
            $this->repoAddress,
            $this->customEnvironmentVariables
        );

        $paymentData = $paymentBuilder->buildPayment(
            $transaction,
            $paymentToken,
            $billingAddressID,
            $shippingAddressID
        );

        # set basic payment data
        $paymentMethodObject->setPayment($paymentData);

        # configure payment specific settings
        $paymentMethodObject = $this->configurePaymentSettings(
            $paymentMethodObject,
            $cleanPaymentMethod,
            $shopID
        );

        # ------------------------------------------------------------------------------------------------------

        $mollieOrder = null;
        $molliePayment = null;

        if ($paymentMethodObject->isOrdersApiEnabled()) {
            $requestBody = $paymentMethodObject->buildBodyOrdersAPI();

            # create a new ORDER in mollie
            # using our orders api request body
            $mollieOrder = $this->gwMollie->createOrder($requestBody);

            # update the orderId field of our transaction
            # this helps us to see the difference to a transaction
            $transaction->setMollieId($mollieOrder->id);

            # also update the line item references
            # every shopware line item is linked to the
            # matching line item in the mollie order
            foreach ($mollieOrder->lines as $index => $line) {
                $item = new OrderLines();

                if ($shopwareOrder instanceof Order) {
                    $item->setOrderId($shopwareOrder->getId());
                }

                $item->setTransactionId($transaction->getId());
                $item->setMollieOrderlineId($line->id);

                $this->orderLinesRepo->save($item);
            }

            # grab our final checkout url for the user
            $checkoutUrl = $mollieOrder->getCheckoutUrl();
        } else {
            $requestBody = $paymentMethodObject->buildBodyPaymentsAPI();

            # create a new PAYMENT in mollie
            # using our payments api request body
            $molliePayment = $this->gwMollie->createPayment($requestBody);

            # update the paymentID field of our transaction
            # this helps us to see the difference to an order
            $transaction->setMolliePaymentId($molliePayment->id);

            # grab our final checkout url for the user
            $checkoutUrl = $molliePayment->getCheckoutUrl();
        }

        # ------------------------------------------------------------------------------------------------------


        # now make sure to do the final steps
        # that are required for all types (payments and orders)
        # and finally update that transaction in our database
        $transaction->setPaymentMethod($paymentMethodName);
        $transaction->setIsShipped(false);

        $this->repoTransactions->save($transaction);

        # ------------------------------------------------------------------------------------------------------

        # reset card token on customer attribute
        $this->creditCardService->setCardToken('');

        # ------------------------------------------------------------------------------------------------------

        # some payment methods do not need the checkout URL
        # in this case, the payment in Mollie is already successfully done
        # and we can immediately redirect to the return page for further validation
        if ($paymentMethodObject->isCheckoutUrlIgnored()) {
            $checkoutUrl = PaymentService::CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED;
        }

        # credit card might come without a checkout URL
        # but this is totally fine for some non-3d secure cards in "earlier times".
        # so we verify if the payment is paid, and in that case, we simply redirect to the return page
        # for further verification.
        if (empty($checkoutUrl)) {
            if ($mollieOrder !== null && $paymentMethodObject->isOrdersApiEnabled()) {
                # ORDERS API
                if ($mollieOrder->method === PaymentMethod::CREDITCARD &&
                    $mollieOrder->status === PaymentStatus::MOLLIE_PAYMENT_PAID) {
                    $checkoutUrl = self::CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED;
                }
            } else {
                # PAYMENTS API
                if ($molliePayment !== null && $molliePayment->method === PaymentMethod::CREDITCARD &&
                    $molliePayment->status === PaymentStatus::MOLLIE_PAYMENT_PAID) {
                    $checkoutUrl = self::CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED;
                }
            }
        }

        # ------------------------------------------------------------------------------------------------------

        return $checkoutUrl;
    }

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @throws ApiException
     * @throws MollieOrderNotFound
     * @return \Mollie\Api\Resources\Order
     */
    public function getMollieOrder(Order $order, Transaction $transaction)
    {
        // Set the correct API key for the order's shop
        $this->setApiKeyForSubShop($order);

        $mollieOrder = $this->gwMollie->getOrder($transaction->getMollieOrderId());

        if (!$mollieOrder instanceof \Mollie\Api\Resources\Order) {
            throw new MollieOrderNotFound($transaction->getMollieOrderId());
        }

        return $mollieOrder;
    }

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @throws ApiException
     * @throws MolliePaymentNotFound
     * @return Payment
     */
    public function getMolliePayment(Order $order, Transaction $transaction)
    {
        // Set the correct API key for the order's shop
        $this->setApiKeyForSubShop($order);

        $molliePayment = $this->gwMollie->getPayment($transaction->getMolliePaymentId());

        if (!$molliePayment instanceof Payment) {
            throw new MolliePaymentNotFound($order->getId());
        }

        return $molliePayment;
    }

    /**
     * Ship the order
     *
     * @param string $mollieId
     * @param Order $shopwareOrder
     *
     * @throws \Exception
     * @return null|bool|\Mollie\Api\Resources\Shipment
     *
     */
    public function sendOrder($mollieId, $shopwareOrder)
    {
        $mollieOrder = null;

        try {
            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->gwMollie->getOrder($mollieId);

            if ($mollieOrder->isShipping()) {
                return (new ArrayCollection($mollieOrder->shipments()->getArrayCopy()))->last();
            }
        } catch (\Exception $ex) {
            throw new \Exception('Order ' . $mollieId . ' could not be found at Mollie.');
        }

        if (!empty($mollieOrder)) {
            $result = null;

            if (!$mollieOrder->isPaid() && !$mollieOrder->isAuthorized()) {
                $this->saveTransactionAsShipped($mollieOrder->orderNumber);
                if ($mollieOrder->isCompleted()) {
                    throw new \Exception('The order is already completed at Mollie.');
                } else {
                    throw new \Exception('The order doesn\'t seem to be paid or authorized.');
                }
            }


            $mollieShipping = new MollieShipping($this->gwMollie, $this->smarty);

            return $mollieShipping->shipOrder($shopwareOrder, $mollieOrder);
        }

        return false;
    }


    /**
     * Retrieve payments result for order
     *
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return array
     */
    public function getPaymentsResultForOrder($mollieOrder = null)
    {
        $paymentsResult = [
            'total' => 0,
            PaymentStatus::MOLLIE_PAYMENT_PAID => 0,
            PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED => 0,
            PaymentStatus::MOLLIE_PAYMENT_PENDING => 0,
            PaymentStatus::MOLLIE_PAYMENT_OPEN => 0,
            PaymentStatus::MOLLIE_PAYMENT_CANCELED => 0,
            PaymentStatus::MOLLIE_PAYMENT_FAILED => 0,
            PaymentStatus::MOLLIE_PAYMENT_EXPIRED => 0
        ];

        if (!empty($mollieOrder) && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
            /** @var \Mollie\Api\Resources\Payment[] $payments */
            $payments = $mollieOrder->payments();

            $paymentsResult['total'] = count($payments);

            foreach ($payments as $payment) {
                if ($payment->isPaid()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_PAID]++;
                }
                if ($payment->isAuthorized()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED]++;
                }
                if ($payment->isPending()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_PENDING]++;
                }
                if ($payment->isOpen()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_OPEN]++;
                }
                if ($payment->isCanceled()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_CANCELED]++;
                }
                if ($payment->isFailed()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_FAILED]++;
                }
                if ($payment->isExpired()) {
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_EXPIRED]++;
                }
            }
        }

        return $paymentsResult;
    }

    /**
     * Reset stock on an order
     *
     * @param Order $order
     * @throws \Exception
     */
    public function resetStock(Order $order)
    {
        /** @var \MollieShopware\Components\Services\BasketService $basketService */
        $basketService = Shopware()->Container()->get('mollie_shopware.basket_service');
        // Reset order quantity
        foreach ($order->getDetails() as $orderDetail) {
            $basketService->resetOrderDetailQuantity($orderDetail);
        }

        // Store order
        Shopware()->Models()->persist($order);
        Shopware()->Models()->flush($order);
    }

    /**
     * Reset invoice and shipping on an order
     *
     * @param Order $order
     * @throws \Exception
     */
    public function resetInvoiceAndShipping(Order $order)
    {
        // Reset shipping and invoice amount
        $order->setInvoiceShipping(0);
        $order->setInvoiceShippingNet(0);
        $order->setInvoiceAmount(0);
        $order->setInvoiceAmountNet(0);

        // Store order
        Shopware()->Models()->persist($order);
        Shopware()->Models()->flush($order);
    }

    /**
     * @param int $orderId
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws TransactionNotFoundException
     */
    private function saveTransactionAsShipped($orderId)
    {
        /** @var EntityManager $entityManager */
        $entityManager = Shopware()->Models();

        /** @var null|Transaction $transaction */
        $transaction = $entityManager->getRepository(Transaction::class)->findOneBy(['orderNumber' => $orderId]);

        if ($transaction === null) {
            throw new TransactionNotFoundException(
                sprintf('with order number %s', $orderId)
            );
        }

        $transaction->setIsShipped(true);
        $entityManager->persist($transaction);
        $entityManager->flush();
    }


    /**
     * @param PaymentInterface $paymentMethodObject
     * @param string $cleanPaymentMethod
     * @param int $shopID
     * @throws ApiException
     * @return ApplePay|BankTransfer|CreditCard|IDeal|PaymentInterface
     */
    private function configurePaymentSettings(PaymentInterface $paymentMethodObject, $cleanPaymentMethod, $shopID)
    {
        # CONFIGURE API METHOD
        $methodType = $this->paymentConfig->getFinalMethodType($cleanPaymentMethod, $shopID);
        # make sure to validate it one more time, because some
        # payment methods have strict guides on what to use
        $worksWithPaymentsApi = PaymentMethodType::isPaymentsApiAllowed($cleanPaymentMethod);
        # if payments is not allowed, or orders api is used, then switch to Orders API
        $useOrdersAPI = ($methodType === PaymentMethodType::ORDERS_API || !$worksWithPaymentsApi);
        $paymentMethodObject->setOrdersApiEnabled($useOrdersAPI);


        # CONFIGURE OPTIONAL EXPIRATION DAYS
        $expirationDays = $this->paymentConfig->getFinalOrderExpiration($cleanPaymentMethod, $shopID);
        if (!empty($expirationDays)) {
            $paymentMethodObject->setExpirationDays((int)$expirationDays);
        }


        # CONFIGURE INDIVIDUAL PAYMENT SPECIFIC DATA
        if ($paymentMethodObject instanceof ApplePay) {
            $applePaymentToken = $this->applePayFactory->createHandler()->getPaymentToken();

            if (!empty($applePaymentToken)) {
                /** @var ApplePay $paymentMethodObject */
                $paymentMethodObject->setPaymentToken($applePaymentToken);

                # if we have a token, then we use apple pay direct
                # and that one has no external mollie page
                $paymentMethodObject->setIgnoreCheckoutURL(true);
            }
        }


        if ($paymentMethodObject instanceof IDeal) {
            # test if we have a current customer (we should have one)
            # if so, get his selected iDeal issuer.
            # if an issuer has been set, then also use it for our payment,
            # otherwise just continue without a prefilled issuer.
            $currentCustomer = $this->customer->getCurrent();

            if ($currentCustomer instanceof Customer) {
                $issuer = $this->idealService->getCustomerIssuer($currentCustomer);
                if (!empty($issuer)) {
                    /** @var IDeal $paymentMethodObject */
                    $paymentMethodObject->setIssuer($issuer);
                }
            }
        }

        if ($paymentMethodObject instanceof CreditCard) {
            $ccToken = $this->creditCardService->getCardToken();

            if (!empty($ccToken)) {
                /** @var CreditCard $paymentMethodObject */
                $paymentMethodObject->setPaymentToken($ccToken);
            }
        }

        if ($paymentMethodObject instanceof BankTransfer) {
            $dueDateDays = $this->config->getBankTransferDueDateDays();

            if (!empty($dueDateDays)) {
                /** @var BankTransfer $paymentMethodObject */
                $paymentMethodObject->setDueDateDays($dueDateDays);
            }

            # if we have enabled the easy bank transfer flow
            # then ignore our checkout url for this payment
            $isEasyBankTransferFlow = $this->paymentConfig->getFinalIsEasyBankTransfer($cleanPaymentMethod, $shopID);
            $paymentMethodObject->setIgnoreCheckoutURL($isEasyBankTransferFlow);
        }

        return $paymentMethodObject;
    }
}
