<?php

namespace MollieShopware\Facades\Notifications;

use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\Order\OrderCancellation;
use MollieShopware\Components\Order\OrderUpdater;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
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
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var MollieStatusConverter
     */
    private $statusConverter;

    /**
     * @var OrderCancellation
     */
    private $orderCancellation;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param TransactionRepository $repoTransactions
     * @param OrderService $orderService
     * @param PaymentService $paymentService
     * @param OrderUpdater $orderUpdater
     * @param MollieStatusConverter $statusConverter
     * @param OrderCancellation $orderCancellation
     */
    public function __construct(LoggerInterface $logger, Config $config, TransactionRepository $repoTransactions, OrderService $orderService, PaymentService $paymentService, OrderUpdater $orderUpdater, MollieStatusConverter $statusConverter, OrderCancellation $orderCancellation)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->repoTransactions = $repoTransactions;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->orderUpdater = $orderUpdater;
        $this->statusConverter = $statusConverter;
        $this->orderCancellation = $orderCancellation;
    }


    /**
     * @param $transactionNumber
     * @throws OrderNotFoundException
     * @throws PaymentStatusNotFoundException
     * @throws TransactionNotFoundException
     * @throws \MollieShopware\Exceptions\MollieOrderNotFound
     * @throws \MollieShopware\Exceptions\OrderStatusNotFoundException
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function onNotify($transactionNumber)
    {
        $this->logger->debug('Incoming Webhook Notification for transaction: ' . $transactionNumber);

        $transaction = $this->repoTransactions->find($transactionNumber);

        if (!$transaction instanceof Transaction) {
            throw new TransactionNotFoundException($transactionNumber);
        }

        if (empty($transaction->getOrderNumber())) {
            # TODO das ist in ordnung wenn webhook schnell kommt. darf nicht logged werden...mhm
            #throw new \Exception('Transaction ' . $transactionNumber . ' has no valid order number!');
            return;
        }

        $order = $this->orderService->getShopwareOrderByNumber($transaction->getOrderNumber());

        if (!$order instanceof Order) {
            throw new OrderNotFoundException($transaction->getOrderNumber());
        }

        $mollieStatus = null;

        if ($transaction->isTypeOrder()) {

            # get the order from the mollie api
            # and extract the status from its data
            $mollieOrder = $this->paymentService->getMollieOrder($order);
            $mollieStatus = $this->statusConverter->getOrderStatus($mollieOrder);

        } else {

            # get the payment from our molli api
            # and extract its status 
            $molliePayment = $this->paymentService->getMolliePayment($order, $transaction->getMolliePaymentId());
            $mollieStatus = $this->statusConverter->getPaymentStatus($molliePayment);
        }

        if ($mollieStatus === null) {
            throw new PaymentStatusNotFoundException('Unable to get status from Mollie for this transaction or order!');
        }


        # -----------------------------------------------------------------------------------------------------
        # UPDATE PAYMENT STATUS + ORDER STATUS

        # verify if our order is failed and could be cancelled.
        #if so, then cancel it
        if (PaymentStatus::isFailedStatus($mollieStatus)) {

            $this->orderCancellation->cancelPlacedOrder($order);

        } else {

            # update our payment status from our
            # notification data
            $this->orderUpdater->updateShopwarePaymentStatus($order, $mollieStatus);

            # if configured, also update the order status
            if ($this->config->updateOrderStatus()) {
                $this->orderUpdater->updateShopwareOrderStatus($order, $mollieStatus);
            }
        }

    }

}
