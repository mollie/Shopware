<?php

namespace MollieShopware\Facades\Notifications;

use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Order\OrderCancellation;
use MollieShopware\Components\Order\OrderUpdater;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Components\Transaction\PaymentStatusResolver;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Models\Order\Order;

class Notifications
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var OrderCancellation
     */
    private $orderCancellation;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var PaymentStatusResolver
     */
    private $paymentStatusResolver;

    /**
     * @var Config\PaymentConfigResolver
     */
    private $paymentConfig;


    /**
     * Notifications constructor.
     * @param LoggerInterface $logger
     * @param Config $config
     * @param TransactionRepository $repoTransactions
     * @param OrderService $orderService
     * @param OrderUpdater $orderUpdater
     * @param OrderCancellation $orderCancellation
     * @param SessionManager $sessionManager
     * @param PaymentStatusResolver $paymentStatusResolver
     * @param Config\PaymentConfigResolver $paymentConfig
     */
    public function __construct(LoggerInterface $logger, Config $config, TransactionRepository $repoTransactions, OrderService $orderService, OrderUpdater $orderUpdater, OrderCancellation $orderCancellation, SessionManager $sessionManager, PaymentStatusResolver $paymentStatusResolver, Config\PaymentConfigResolver $paymentConfig)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->repoTransactions = $repoTransactions;
        $this->orderService = $orderService;
        $this->orderUpdater = $orderUpdater;
        $this->orderCancellation = $orderCancellation;
        $this->sessionManager = $sessionManager;
        $this->paymentStatusResolver = $paymentStatusResolver;
        $this->paymentConfig = $paymentConfig;
    }


    /**
     * @param $transactionID
     * @param $paymentID
     * @throws OrderNotFoundException
     * @throws PaymentStatusNotFoundException
     * @throws TransactionNotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Enlight_Event_Exception
     * @throws \MollieShopware\Exceptions\OrderStatusNotFoundException
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function onNotify($transactionID, $paymentID)
    {
        $this->logger->info('Incoming Webhook Notification for transaction: ' . $transactionID . ' and payment: ' . $paymentID);


        # -----------------------------------------------------------------------------------------------------
        # LOAD TRANSACTION
        $transaction = $this->repoTransactions->find($transactionID);

        if (!$transaction instanceof Transaction) {
            throw new TransactionNotFoundException($transactionID);
        }


        # -----------------------------------------------------------------------------------------------------
        # GET PAYMENT STATUS FROM MOLLIE
        $molliePaymentStatus = $this->paymentStatusResolver->fetchPaymentStatus($transaction);


        # -----------------------------------------------------------------------------------------------------
        # DELETE PAYMENT TOKEN ON EXPIRED WEBHOOK
        # if the payment is expired, we can simply delete the token, the payment will never come again
        if ($molliePaymentStatus === PaymentStatus::MOLLIE_PAYMENT_EXPIRED) {
            $this->sessionManager->deleteSessionToken($transaction);
        }


        # -----------------------------------------------------------------------------------------------------
        # IF WE DON'T HAVE AN ORDER YET, STOP OUR WEBHOOK
        if (empty($transaction->getOrderNumber())) {
            # if we have a notification without an order
            # then the webhook is "too fast" so we just skip it for now.
            # the frontend will create the order and process the details in that case
            return;
        }

        $order = $this->orderService->getShopwareOrderByNumber($transaction->getOrderNumber());

        if (!$order instanceof Order) {
            throw new OrderNotFoundException('Order with number: ' . $transaction->getOrderNumber() . ' not found!');
        }

        # -----------------------------------------------------------------------------------------------------
        # UPDATE PAYMENT STATUS + ORDER STATUS

        if (PaymentStatus::isFailedStatus($molliePaymentStatus)) {
            $this->orderCancellation->cancelPlacedOrder($order);

        } else {

            # update our payment status from our notification data
            $this->orderUpdater->updateShopwarePaymentStatus($order, $molliePaymentStatus);

            # if configured, also update the order status
            if ($this->config->updateOrderStatus()) {
                $this->orderUpdater->updateShopwareOrderStatus($order, $molliePaymentStatus);
            }
        }


        # -----------------------------------------------------------------------------------------------------
        # ALWAYS DELETE PAYMENT TOKEN IF AN ORDER EXISTS
        # it should actually be already removed, but let's be better safe than sorry :)
        # but only if we create orders AFTER the payment.
        # otherwise we would have a race condition, because our order already exists when we
        # would come back without a session in the frontend. this webhooks is faster
        # and so it wouldn't be possible to restore it.
        $orderCreation = $this->paymentConfig->getFinalOrderCreation($transaction->getPaymentMethod(), $transaction->getShopId());

        if ($orderCreation === OrderCreationType::AFTER_PAYMENT) {
            $this->sessionManager->deleteSessionToken($transaction);
        }


        $this->logger->debug('Webhook Notification successfully processed for transaction: ' . $transactionID . ' and payment: ' . $paymentID);
    }

}
