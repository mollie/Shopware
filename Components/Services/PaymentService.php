<?php

namespace MollieShopware\Components\Services;

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
     * credit cards without a 3d secure (isnt allowed except on test systems)
     * do not have a checkout URL. in that case the
     * payment is immediately PAID and thus we "just" redirect to the finish page.
     * We need this constants, because the response for the controller is a
     * string and i dont want to touch anything else.
     */
    const CHECKOUT_URL_CC_NON3D_SECURE = 'OK_NON_3dSecure';


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


        # if we have no checkout url
        # but our payment is valid, "paid" and done with a payment method "creditcard"
        # then we have the case that its a "non 3d secure" card, and thus
        # its ok that our checkout url is empty. We just "finish" the order in the controller action.
        if (empty($checkoutUrl)) {

            if ($molliePayment instanceof Payment &&
                $molliePayment->status === PaymentStatus::MOLLIE_PAYMENT_PAID &&
                $molliePayment->method === PaymentMethod::CREDITCARD) {
                # assign our constant which helps us
                # to finish the order in the controller action
                $checkoutUrl = self::CHECKOUT_URL_CC_NON3D_SECURE;
            }
        }

        return $checkoutUrl;
    }

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @param array $orderDetails
     * @return string
     * @throws ApiException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function startOrderTransaction(Order $order, Transaction $transaction, $orderDetails = array())
    {
        // variables
        $checkoutUrl = '';
        $mollieOrder = null;
        $molliePayment = null;
        $paymentMethod = $order->getPayment()->getName();

        if (strstr($paymentMethod, 'klarna') ||
            $this->config->useOrdersApiOnlyWhereMandatory() == false) {

            // prepare the order for mollie
            $mollieOrderPrepared = $this->prepareOrder($order, $orderDetails);

            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $this->apiClient->orders->create(
                $mollieOrderPrepared
            );

            /** @var \MollieShopware\Models\OrderLinesRepository $orderLinesRepo */
            $orderLinesRepo = Shopware()->container()->get('models')
                ->getRepository('\MollieShopware\Models\OrderLines');

            foreach ($mollieOrder->lines as $index => $line) {
                // create new item
                $item = new OrderLines();

                // set variables
                $item->setOrderId($order->getId());
                $item->setMollieOrderlineId($line->id);

                // save item
                $orderLinesRepo->save($item);
            }
        } else {
            // prepare the payment for mollie
            $molliePaymentPrepared = $this->preparePayment($order);

            /** @var \Mollie\Api\Resources\Payment $molliePayment */
            $molliePayment = $this->apiClient->payments->create(
                $molliePaymentPrepared
            );
        }

        /** @var TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')
            ->getRepository('\MollieShopware\Models\Transaction');

        $transaction->setOrderId($order->getId());

        if (!empty($mollieOrder)) {
            $transaction->setMollieId($mollieOrder->id);
            $checkoutUrl = $mollieOrder->getCheckoutUrl();
        }

        if (!empty($molliePayment)) {
            $transaction->setMolliePaymentId(($molliePayment->id));
            $checkoutUrl = $molliePayment->getCheckoutUrl();
        }

        $transactionRepo->save($transaction);

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
     * @return \Mollie\Api\Resources\Order
     * @throws ApiException
     * @throws MollieOrderNotFound
     */
    public function getMollieOrder(Order $order)
    {
        /** @var TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(Transaction::class);

        /** @var Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

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
     * @param string $paymentId
     * @return Payment
     * @throws ApiException
     */
    public function getMolliePayment(Order $order, $paymentId = '')
    {
        /** @var TransactionRepository $transactionRepo */
        $transactionRepo = Shopware()->container()->get('models')->getRepository(
            Transaction::class
        );

        /** @var Transaction $transaction */
        $transaction = $transactionRepo->getMostRecentTransactionForOrder($order);

        if (empty($paymentId))
            $paymentId = $transaction->getMolliePaymentId();

        // Set the correct API key for the order's shop
        $this->setApiKeyForSubShop($order);

        /** @var \Mollie\Api\Resources\Payment $molliePayment */
        $molliePayment = $this->apiClient->payments->get(
            $paymentId
        );

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
     * Check if the payments for an order failed
     *
     * @param Order $order
     * @param string $status
     *
     * @return bool
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function isOrderPaymentsStatus(Order $order, $status)
    {
        /** @var \Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = $this->getMollieOrder($order);
        $paymentsResult = $this->getPaymentsResultForOrder($mollieOrder);

        // fully failed
        if ($paymentsResult['total'] > 0) {
            if ($paymentsResult[$status] == $paymentsResult['total'])
                return true;
        }

        return false;
    }

    /**
     * Check the order status and redirect the user if possible
     * also, if the payment is complete or authorized, send the confirmation e-mail
     *
     * @param Order $order
     * @param string $status
     * @param boolean $returnResult
     * @return mixed
     * @throws \Exception
     */
    public function updateShopwareOrderPaymentStatus(Order $order, $status, $returnResult = false, $type = 'payment')
    {
        // get the order module
        $sOrder = Shopware()->Modules()->Order();

        // the order is completed
        if ($status === PaymentStatus::MOLLIE_PAYMENT_COMPLETED) {
            if ($type === 'order' && $this->config->updateOrderStatus()) {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_COMPLETED,
                    $this->config->isPaymentStatusMailEnabled()
                );
            }

            if ($returnResult) {
                return true;
            }
        }

        // the order or payment is paid
        if ($status === PaymentStatus::MOLLIE_PAYMENT_PAID) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_COMPLETELY_PAID,
                $this->config->isPaymentStatusMailEnabled()
            );

            if ($returnResult) {
                return true;
            }
        }

        if ($status === PaymentStatus::MOLLIE_PAYMENT_REFUNDED) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_RE_CREDITING,
                $this->config->isPaymentStatusMailEnabled()
            );

            if ($returnResult) {
                return true;
            }
        }

        if ($status === PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_RE_CREDITING,
                $this->config->isPaymentStatusMailEnabled()
            );

            if ($returnResult) {
                return true;
            }
        }

        // the order or payment is authorized
        if ($status === PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                $this->config->getAuthorizedPaymentStatusId(),
                $this->config->isPaymentStatusMailEnabled()
            );

            if ($returnResult) {
                return true;
            }
        }

        // the payment is delayed
        if ($status === PaymentStatus::MOLLIE_PAYMENT_DELAYED) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_DELAYED,
                $this->config->isPaymentStatusMailEnabled()
            );

            if ($returnResult) {
                return true;
            }
        }

        // the payment is open
        if ($status === PaymentStatus::MOLLIE_PAYMENT_OPEN) {
            $sOrder->setPaymentStatus(
                $order->getId(),
                Status::PAYMENT_STATE_OPEN,
                $this->config->isPaymentStatusMailEnabled()
            );

            if ($returnResult) {
                return true;
            }
        }

        // the order or payment is canceled
        if ($status === PaymentStatus::MOLLIE_PAYMENT_CANCELED) {
            if ($type === 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                    $this->config->isPaymentStatusMailEnabled()
                );
            }

            if (
                $this->config->cancelFailedOrders()
                || (
                    $type === 'order'
                    && $this->config->updateOrderStatus()
                )
            ) {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_CANCELLED_REJECTED,
                    $this->config->isPaymentStatusMailEnabled()
                );

                if ($this->config->autoResetStock()) {
                    $this->resetStock($order);
                }
            }

            if ($returnResult) {
                return true;
            }
        }

        // the payment has failed or is expired
        if ($status === PaymentStatus::MOLLIE_PAYMENT_FAILED ||
            $status === PaymentStatus::MOLLIE_PAYMENT_EXPIRED) {
            if ($type === 'payment') {
                $sOrder->setPaymentStatus(
                    $order->getId(),
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
                    $this->config->isPaymentStatusMailEnabled()
                );
            }

            if ($this->config->cancelFailedOrders()) {
                $sOrder->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_CANCELLED_REJECTED,
                    $this->config->isPaymentStatusMailEnabled()
                );

                if ($this->config->autoResetStock()) {
                    $this->resetStock($order);
                }
            }

            if ($returnResult) {
                return true;
            }
        }
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
        } catch (\Exception $ex) {
            throw new \Exception('Order ' . $mollieId . ' could not be found at Mollie.');
        }

        if (!empty($mollieOrder)) {
            $result = null;

            if (!$mollieOrder->isPaid() && !$mollieOrder->isAuthorized()) {
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
        if ($this->config->autoResetStock()) {
            // Cancel failed orders
            /** @var \MollieShopware\Components\Services\BasketService $basketService */
            $basketService = Shopware()->Container()->get('mollie_shopware.basket_service');
            // Reset order quantity
            foreach ($order->getDetails() as $orderDetail) {
                $basketService->resetOrderDetailQuantity($orderDetail);
            }
            // Reset shipping and invoice amount
            if ($this->config->resetInvoiceAndShipping()) {
                $order->setInvoiceShipping(0);
                $order->setInvoiceShippingNet(0);
                $order->setInvoiceAmount(0);
                $order->setInvoiceAmountNet(0);
            }
        }

        // Store order
        Shopware()->Models()->persist($order);
        Shopware()->Models()->flush($order);
    }
}
