<?php

namespace MollieShopware\Components\Services;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectFactory;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\CustomConfig\CustomConfig;
use MollieShopware\Components\MollieApi\LineItemsBuilder;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Exceptions\MollieOrderNotFound;
use MollieShopware\Exceptions\MolliePaymentNotFound;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\OrderLines;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

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
     * @var MollieApiFactory $apiFactory
     */
    protected $apiFactory;

    /**
     * @var \Mollie\Api\MollieApiClient $apiClient
     */
    protected $apiClient;

    /**
     * @var Config $config
     */
    protected $config;

    /**
     * @var array
     */
    protected $customEnvironmentVariables;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;


    /**
     * @param MollieApiFactory $apiFactory
     * @param Config $config
     * @param array $customEnvironmentVariables
     * @param MollieGatewayInterface $gwMollie
     * @throws ApiException
     */
    public function __construct(MollieApiFactory $apiFactory, Config $config, MollieGatewayInterface $gwMollie, array $customEnvironmentVariables)
    {
        $this->apiFactory = $apiFactory;
        $this->apiClient = $apiFactory->create();
        $this->config = $config;
        $this->gwMollie = $gwMollie;
        $this->customEnvironmentVariables = $customEnvironmentVariables;
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
        $this->apiClient = $client;
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
     * @param $paymentMethod
     * @param Transaction $transaction
     * @return string|null
     * @throws ApiException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function startMollieSession($paymentMethod, Transaction $transaction)
    {
        $shopwareOrder = null;

        /** @var TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository('\MollieShopware\Models\Transaction');

        /** @var \Shopware\Models\Order\Repository $orderRepo */
        $orderRepo = Shopware()->Models()->getRepository(Order::class);

        $orderLinesRepo = Shopware()->container()->get('models')->getRepository('\MollieShopware\Models\OrderLines');


        # check if we have an existing order in 
        # shopware for our transaction
        # if so load it and use it as meta data information
        if (!empty($transaction->getOrderId())) {

            $shopwareOrder = $orderRepo->find($transaction->getOrderId());

            $transaction->setOrderId($shopwareOrder->getId());
        }


        /** @var bool $useOrdersAPI */
        $useOrdersAPI = strstr($paymentMethod, 'klarna') || $this->config->useOrdersApiOnlyWhereMandatory() == false;


        if ($useOrdersAPI) {

            $requestData = $this->prepareRequest($paymentMethod, $transaction, true);
            $mollieOrder = $this->apiClient->orders->create($requestData);

            foreach ($mollieOrder->lines as $index => $line) {

                $item = new OrderLines();

                if ($shopwareOrder instanceof Order) {
                    $item->setOrderId($shopwareOrder->getId());
                }

                $item->setTransactionId($transaction->getId());
                $item->setMollieOrderlineId($line->id);

                $orderLinesRepo->save($item);
            }

        } else {

            $requestData = $this->prepareRequest($paymentMethod, $transaction);
            $molliePayment = $this->apiClient->payments->create($requestData);
        }


        if ($useOrdersAPI) {

            $transaction->setMollieId($mollieOrder->id);
            $checkoutUrl = $mollieOrder->getCheckoutUrl();

        } else {

            $transaction->setMolliePaymentId($molliePayment->id);
            $checkoutUrl = $molliePayment->getCheckoutUrl();
        }


        $transaction->setPaymentMethod($paymentMethod);
        $transaction->setIsShipped(false);


        $transactionRepo->save($transaction);


        // Reset card token on customer attribute
        /** @var \MollieShopware\Components\Services\CreditCardService $creditCardService */
        $creditCardService = Shopware()->Container()->get('mollie_shopware.credit_card_service');
        $creditCardService->setCardToken('');


        # some payment methods with credit card, e.g. "non-3d-secure" or apple pay
        # have no checkout url, because they might already be paid immediately.
        # so we check for their payment methods and return our special checkout URL
        # to tell our calling function that we should redirect to the return url immediately.
        if (empty($checkoutUrl)) {

            if ($useOrdersAPI) {

                if ($mollieOrder->method === PaymentMethod::CREDITCARD &&
                    $mollieOrder->status === PaymentStatus::MOLLIE_PAYMENT_PAID) {
                    $checkoutUrl = self::CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED;
                }

            } else {

                if ($molliePayment->method === PaymentMethod::CREDITCARD &&
                    $molliePayment->status === PaymentStatus::MOLLIE_PAYMENT_PAID) {
                    $checkoutUrl = self::CHECKOUT_URL_NO_REDIRECT_TO_MOLLIE_REQUIRED;
                }
            }
        }

        return $checkoutUrl;
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
     * @param Order $order
     * @param Transaction $transaction
     * @return \Mollie\Api\Resources\Order
     * @throws ApiException
     * @throws MollieOrderNotFound
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
     * @return Payment
     * @throws ApiException
     * @throws MolliePaymentNotFound
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
     * @param $paymentMethod
     * @param Transaction $transaction
     * @param false $ordersApi
     * @return array
     * @throws \Exception
     */
    private function prepareRequest($paymentMethod, Transaction $transaction, $ordersApi = false)
    {
        // variables
        $molliePrepared = null;
        $paymentParameters = [];

        // get webhook and redirect URLs
        $redirectUrl = $this->prepareRedirectUrl($transaction->getId());
        $webhookUrl = $this->prepareWebhookURL($transaction->getId());

        $paymentParameters['webhookUrl'] = $webhookUrl;

        if (substr($paymentMethod, 0, strlen('mollie_')) == 'mollie_')
            $paymentMethod = substr($paymentMethod, strlen('mollie_'));

        // set method specific parameters
        $paymentParameters = $this->preparePaymentParameters(
            $paymentMethod,
            $paymentParameters
        );


        // create prepared order array
        $molliePrepared = [
            'amount' => $this->getPriceArray(
                $transaction->getCurrency(),
                round($transaction->getTotalAmount(), 2)
            ),
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'locale' => $transaction->getLocale(),
            'method' => $paymentMethod,
        ];

        $paymentDescription = (string)(time() . $transaction->getId() . substr($transaction->getBasketSignature(), -4));

        // add extra parameters depending on using the Orders API or the Payments API
        if ($ordersApi) {
            // get order lines
            $lineItemBuilder = new LineItemsBuilder();
            $orderLines = $lineItemBuilder->buildLineItems($transaction);

            // set order parameters
            $molliePrepared['orderNumber'] = strlen($transaction->getOrderNumber()) ?
                (string)$transaction->getOrderNumber() : $paymentDescription;

            $molliePrepared['lines'] = $orderLines;
            $molliePrepared['billingAddress'] = $this->getAddress(
                $transaction->getCustomer()->getDefaultBillingAddress(),
                $transaction->getCustomer()
            );
            $molliePrepared['shippingAddress'] = $this->getAddress(
                $transaction->getCustomer()->getDefaultShippingAddress(),
                $transaction->getCustomer()
            );
            $molliePrepared['payment'] = $paymentParameters;
            $molliePrepared['metadata'] = [];
        } else {
            // add description
            $molliePrepared['description'] = strlen($transaction->getOrderNumber()) ? 'Order ' .
                $transaction->getOrderNumber() : 'Transaction ' . $paymentDescription;

            // add billing e-mail address
            if ($paymentMethod == PaymentMethod::BANKTRANSFER || $paymentMethod == PaymentMethod::P24)
                $molliePrepared['billingEmail'] = $transaction->getCustomer()->getEmail();

            // prepare payment parameters
            $molliePrepared = $this->preparePaymentParameters(
                $paymentMethod,
                $molliePrepared
            );
        }


        if ((string)$paymentMethod === PaymentMethod::APPLEPAY_DIRECT) {
            # assign the payment token
            $molliePrepared['method'] = PaymentMethod::APPLE_PAY;
        }

        return $molliePrepared;
    }

    /**
     * @param $currency
     * @param $amount
     * @param int $decimals
     * @return array
     */
    private function getPriceArray($currency, $amount, $decimals = 2)
    {
        return [
            'currency' => $currency,
            'value' => number_format($amount, $decimals, '.', ''),
        ];
    }

    /**
     * @param $address
     * @param \Shopware\Models\Customer\Customer $customer
     * @return array
     */
    private function getAddress($address, \Shopware\Models\Customer\Customer $customer)
    {
        $country = $address->getCountry();

        return [
            'title' => $address->getSalutation() . '.',
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $customer->getEmail(),
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $country ? $country->getIso() : 'NL',
        ];
    }

    /**
     * @param $number
     * @return mixed|string
     */
    private function prepareRedirectUrl($number)
    {
        $assembleData = [
            'controller' => 'Mollie',
            'action' => 'return',
            'transactionNumber' => $number,
            'forceSecure' => true
        ];

        $url = Shopware()->Front()->Router()->assemble($assembleData);

        return $url;
    }

    /**
     * @param $number
     * @return mixed|string
     * @throws \Exception
     */
    private function prepareWebhookURL($number)
    {
        $assembleData = [
            'controller' => 'Mollie',
            'action' => 'notify',
            'transactionNumber' => $number,
            'forceSecure' => true
        ];

        $url = Shopware()->Front()->Router()->assemble($assembleData);


        # check if we have a custom
        # configuration for mollie and see
        # if we have to use the custom shop base URL
        $customConfig = new CustomConfig($this->customEnvironmentVariables);

        # if we have a custom webhook URL
        # make sure to replace the original shop urls 
        # with the one we provide in here
        if (!empty($customConfig->getShopDomain())) {

            $host = Shopware()->Shop()->getHost();

            # replace old domain with
            # new custom domain
            $url = str_replace($host, $customConfig->getShopDomain(), $url);
        }

        return $url;
    }

    /**
     * Get the id of the chosen ideal issuer from database
     *
     * @return string
     */
    protected function getIdealIssuer()
    {
        /** @var IdealService $idealService */
        $idealService = Shopware()->Container()->get('mollie_shopware.ideal_service');
        return $idealService->getSelectedIssuer();
    }

    /**
     * Returns the token for a credit card payment.
     *
     * @return string
     */
    protected function getCreditCardToken()
    {
        /** @var CreditCardService $creditCardService */
        $creditCardService = Shopware()->Container()->get('mollie_shopware.credit_card_service');
        return $creditCardService->getCardToken();
    }

    /**
     * @return string
     * @throws ApiException
     */
    protected function getApplePayPaymentToken()
    {
        /** @var ApplePayDirectFactory $applePayFactory */
        $applePayFactory = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.factory');
        return $applePayFactory->createHandler()->getPaymentToken();
    }


    /**
     * Ship the order
     *
     * @param string $mollieId
     *
     * @return bool|\Mollie\Api\Resources\Shipment|null
     *
     * @throws \Exception
     */
    public function sendOrder($mollieId)
    {
        $mollieOrder = null;

        try {
            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->apiClient->orders->get($mollieId);

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

            try {
                $result = $mollieOrder->shipAll();
            } catch (\Exception $ex) {
                throw new \Exception('The order can\'t be shipped.');
            }

            return $result;
        }

        return false;
    }

    /**
     * Prepare the payment parameters based on the payment method's requirements
     *
     * @param $paymentMethod
     * @param array $paymentParameters
     *
     * @return array
     */
    private function preparePaymentParameters($paymentMethod, array $paymentParameters)
    {
        if ((string)$paymentMethod === PaymentMethod::IDEAL) {
            $paymentParameters['issuer'] = $this->getIdealIssuer();
        }

        if ((string)$paymentMethod === PaymentMethod::CREDITCARD) {
            if ($this->config->enableCreditCardComponent() === true &&
                (string)$this->getCreditCardToken() !== '') {
                $paymentParameters['cardToken'] = $this->getCreditCardToken();
            }
        }

        if ((string)$paymentMethod === PaymentMethod::APPLEPAY_DIRECT) {
            # assign the payment token
            $paymentParameters["applePayPaymentToken"] = $this->getApplePayPaymentToken();
        }

        if ((string)$paymentMethod === PaymentMethod::BANKTRANSFER) {

            $dueDateDays = $this->config->getBankTransferDueDateDays();

            if (!empty($dueDateDays)) {
                $paymentParameters['dueDate'] = date('Y-m-d', strtotime(' + ' . $dueDateDays . ' day'));
            }
        }

        return $paymentParameters;
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
            PaymentStatus::MOLLIE_PAYMENT_DELAYED => 0,
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
                if ($payment->isPaid())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_PAID]++;
                if ($payment->isAuthorized())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED]++;
                if ($payment->isPending())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_DELAYED]++;
                if ($payment->isOpen())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_OPEN]++;
                if ($payment->isCanceled())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_CANCELED]++;
                if ($payment->isFailed())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_FAILED]++;
                if ($payment->isExpired())
                    $paymentsResult[PaymentStatus::MOLLIE_PAYMENT_EXPIRED]++;
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

        /** @var Transaction|null $transaction */
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
}
