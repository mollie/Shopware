<?php

namespace MollieShopware\Facades\Notifications;

use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\Order\OrderCancellation;
use MollieShopware\Components\Order\OrderUpdater;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Components\SessionSnapshot\SessionSnapshotManager;
use MollieShopware\Components\Transaction\PaymentStatusResolver;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Models\SessionSnapshot\Repository;
use MollieShopware\Models\SessionSnapshot\SessionSnapshot;
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
     * @var SessionSnapshotManager
     */
    private $sessionSnapshotRemover;

    /**
     * @var PaymentStatusResolver
     */
    private $paymentStatusResolver;


    /**
     * Notifications constructor.
     * @param LoggerInterface $logger
     * @param Config $config
     * @param TransactionRepository $repoTransactions
     * @param OrderService $orderService
     * @param OrderUpdater $orderUpdater
     * @param OrderCancellation $orderCancellation
     * @param SessionSnapshotManager $sessionSnapshotManager
     * @param PaymentStatusResolver $paymentStatusResolver
     */
    public function __construct(LoggerInterface $logger, Config $config, TransactionRepository $repoTransactions, OrderService $orderService, OrderUpdater $orderUpdater, OrderCancellation $orderCancellation, SessionSnapshotManager $sessionSnapshotManager, PaymentStatusResolver $paymentStatusResolver)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->repoTransactions = $repoTransactions;
        $this->orderService = $orderService;
        $this->orderUpdater = $orderUpdater;
        $this->orderCancellation = $orderCancellation;
        $this->sessionSnapshotRemover = $sessionSnapshotManager;
        $this->paymentStatusResolver = $paymentStatusResolver;
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
        # DELETE SNAPSHOT SESSION IF READY TO PURGE
        $sessionSnapshot = $this->sessionSnapshotRemover->findSnapshot($transaction->getId());

        # if the payment is expired, we can simply delete the snapshot
        # it will never come again
        # TODO als erstes expired prüfen für performance improvement
        if ($sessionSnapshot instanceof SessionSnapshot && $molliePaymentStatus === PaymentStatus::MOLLIE_PAYMENT_EXPIRED) {
            $this->sessionSnapshotRemover->delete($sessionSnapshot);
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
        # ALWAYS DELETE SNAPSHOTS IF AN ORDER EXISTS
        if ($sessionSnapshot instanceof SessionSnapshot) {
            $this->sessionSnapshotRemover->delete($sessionSnapshot);
        }

        $this->logger->debug('Webhook Notification successfully processed for transaction: ' . $transactionID . ' and payment: ' . $paymentID);
    }

}
