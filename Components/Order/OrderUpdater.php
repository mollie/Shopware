<?php

namespace MollieShopware\Components\Order;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Events\Events;
use MollieShopware\Components\StatusMapping\OrderTransactionMapper;
use MollieShopware\Components\StatusMapping\PaymentTransactionMapper;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use Psr\Log\LoggerInterface;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\History;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
use sOrder;

class OrderUpdater
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
     * @var sOrder
     */
    private $sOrder;

    /**
     * @var ContainerAwareEventManager
     */
    private $eventManager;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @param Config $config
     * @param $sOrder
     * @param $eventManager
     * @param ModelManager $modelManager
     * @param $logger
     */
    public function __construct(Config $config, $sOrder, $eventManager, ModelManager $modelManager, $logger)
    {
        $this->config = $config;
        $this->sOrder = $sOrder;
        $this->eventManager = $eventManager;
        $this->modelManager = $modelManager;
        $this->logger = $logger;
    }


    /**
     * @param Order $order
     * @param $status
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PaymentStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    public function updateShopwarePaymentStatus(Order $order, $status)
    {
        $statusDidChange = $this->updatePaymentStatus(
            $order,
            $status,
            $this->config->isPaymentStatusMailEnabled()
        );

        if (!$statusDidChange) {
            return;
        }

        # update our status history and add the
        # comment that is was done by Mollie
        $this->updateOrderHistoryComment($order);
    }

    /**
     * @param Order $order
     * @param $status
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PaymentStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    public function updateShopwarePaymentStatusWithoutMail(Order $order, $status)
    {
        $statusDidChange = $this->updatePaymentStatus(
            $order,
            $status,
            false
        );

        if (!$statusDidChange) {
            return;
        }

        # update our status history and add the
        # comment that is was done by Mollie
        $this->updateOrderHistoryComment($order);
    }

    /**
     * @param Order $order
     * @param $status
     * @param bool $isOrderTransaction
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws OrderStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    public function updateShopwareOrderStatus(Order $order, $status, $isOrderTransaction)
    {
        $statusDidChange = $this->updateOrderStatus(
            $order,
            $status,
            $this->config->isPaymentStatusMailEnabled(),
            $isOrderTransaction
        );

        if (!$statusDidChange) {
            return;
        }

        # update our status history and add the
        # comment that is was done by Mollie
        $this->updateOrderHistoryComment($order);
    }

    /**
     * @param Order $order
     * @param $status
     * @param bool $isOrderTransaction
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws OrderStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    public function updateShopwareOrderStatusWithoutMail(Order $order, $status, $isOrderTransaction)
    {
        $statusDidChange = $this->updateOrderStatus(
            $order,
            $status,
            false,
            $isOrderTransaction
        );

        if (!$statusDidChange) {
            return;
        }

        # update our status history and add the
        # comment that is was done by Mollie
        $this->updateOrderHistoryComment($order);
    }

    /**
     * This function updates our latest order history entry
     * by appending "Mollie" as the invoker within
     * the comment column.
     *
     * @param Order $order
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function updateOrderHistoryComment(Order $order)
    {
        /** @var History $lastEntry */
        $lastEntry = $order->getHistory()->last();

        if (!empty($lastEntry->getComment())) {
            return;
        }

        $lastEntry->setComment('Status updated by Mollie');
        $this->modelManager->flush($lastEntry);
    }

    /**
     * @param Order $order
     * @param $status
     * @param $sendMail
     * @return bool
     * @throws PaymentStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    private function updatePaymentStatus(Order $order, $status, $sendMail)
    {
        $newShopwareStatus = null;
        $ignoreState = false;

        switch ($status) {

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
                $newShopwareStatus = Status::PAYMENT_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $newShopwareStatus = $this->config->getAuthorizedPaymentStatusId();
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PENDING:
                $newShopwareStatus = Status::PAYMENT_STATE_DELAYED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PAID:
                $newShopwareStatus = Status::PAYMENT_STATE_COMPLETELY_PAID;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $newShopwareStatus = Status::PAYMENT_STATE_RE_CREDITING;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $newShopwareStatus = Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                # impact on the payment entry in shopware
                # these are only states about the order
                $ignoreState = true;
                break;
        }


        $previousShopwareStatus = $newShopwareStatus;

        # send a filter event, so developer can adjust the status that will
        # be used for the shopware payment status
        $newShopwareStatus = $this->eventManager->filter(
            Events::UPDATE_ORDER_PAYMENT_STATUS,
            $newShopwareStatus,
            array(
                'molliePaymentStatus' => $status,
                'order' => $order,
            )
        );

        if ($previousShopwareStatus !== $newShopwareStatus) {
            $this->logger->info('Filter Event changed Payment Status for Order ' . $order->getNumber(),
                array(
                    'data' => array(
                        'previousStatus' => $previousShopwareStatus,
                        'newStatus' => $newShopwareStatus
                    )
                )
            );

            # avoid state ignoring, because we have
            # a custom handling now. so process everything the
            # other plugin says
            $ignoreState = false;
        }

        if ($ignoreState) {
            return false;
        }

        if ($newShopwareStatus === null) {
            throw new PaymentStatusNotFoundException('Unable to get Shopware Payment Status from Mollie Payment Status: ' . $status);
        }

        # verify if our status is indeed changing
        # if not, simply do nothing
        $isNewStatus = (string)$order->getPaymentStatus()->getId() !== (string)$newShopwareStatus;

        if (!$isNewStatus) {
            return false;
        }

        $this->sOrder->setPaymentStatus(
            $order->getId(),
            $newShopwareStatus,
            $sendMail
        );

        return true;
    }

    /**
     * @param Order $order
     * @param $mollieStatus
     * @param $sendMail
     * @param bool $isOrderTransaction
     * @return bool
     * @throws OrderStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    private function updateOrderStatus(Order $order, $mollieStatus, $sendMail, $isOrderTransaction)
    {
        if ($isOrderTransaction) {
            $result = OrderTransactionMapper::mapStatus($mollieStatus);
        } else {
            $result = PaymentTransactionMapper::mapStatus($mollieStatus);
        }

        $targetState = $result->getTargetStatus();
        $ignoreState = $result->isIgnoreState();

        $previousShopwareStatus = $targetState;

        # send a filter event, so developer can adjust the status that will
        # be used for the shopware order status
        $newShopwareStatus = $this->eventManager->filter(
            Events::UPDATE_ORDER_STATUS,
            $targetState,
            array(
                'mollieOrderStatus' => $mollieStatus,
                'order' => $order,
            )
        );

        if ($previousShopwareStatus !== $newShopwareStatus) {
            $this->logger->info('Filter Event changed Order Status for Order ' . $order->getNumber(),
                array(
                    'data' => array(
                        'previousStatus' => $previousShopwareStatus,
                        'newStatus' => $newShopwareStatus
                    )
                )
            );

            # avoid state ignoring, because we have
            # a custom handling now. so process everything the
            # other plugin says
            $ignoreState = false;
        }


        if ($ignoreState) {
            return false;
        }

        if ($newShopwareStatus === null) {
            throw new OrderStatusNotFoundException($mollieStatus);
        }

        # verify if our status is indeed changing
        # if not, simply do nothing
        $isNewStatus = (string)$order->getOrderStatus()->getId() !== (string)$newShopwareStatus;

        if (!$isNewStatus) {
            return false;
        }

        $this->sOrder->setOrderStatus(
            $order->getId(),
            $newShopwareStatus,
            $sendMail
        );

        return true;
    }

}