<?php

namespace MollieShopware\Components\Order;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use MollieShopware\Components\Config;
use MollieShopware\Components\StatusConverter\OrderStatusConverter;
use MollieShopware\Components\StatusConverter\PaymentStatusConverter;
use MollieShopware\Components\Validator\PaymentStatusMailValidator;
use MollieShopware\Events\Events;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use Psr\Log\LoggerInterface;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\History;
use Shopware\Models\Order\Order;
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
     * @var EntityRepository
     */
    private $repoHistory;

    /**
     * @var PaymentStatusMailValidator
     */
    private $paymentStatusMailValidator;


    /**
     * OrderUpdater constructor.
     * @param Config $config
     * @param $eventManager
     * @param ModelManager $modelManager
     * @param $logger
     */
    public function __construct(Config $config, $eventManager, ModelManager $modelManager, $logger)
    {
        $this->config = $config;
        $this->sOrder = Shopware()->Modules()->Order();
        $this->eventManager = $eventManager;
        $this->modelManager = $modelManager;
        $this->logger = $logger;

        $this->repoHistory = $this->modelManager->getRepository(History::class);

        $this->paymentStatusMailValidator = new PaymentStatusMailValidator($config);
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
        $shouldSendPaymentMail = $this->paymentStatusMailValidator->shouldSendPaymentStatusMail($order, $status);

        $statusDidChange = $this->updatePaymentStatus(
            $order,
            $status,
            $shouldSendPaymentMail
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
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws OrderStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    public function updateShopwareOrderStatus(Order $order, $status)
    {
        $statusDidChange = $this->updateOrderStatus(
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
     * @throws OrderStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    public function updateShopwareOrderStatusWithoutMail(Order $order, $status)
    {
        $statusDidChange = $this->updateOrderStatus(
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
        # we have to "live" reload the history entries.
        # if payment AND order status would be written both, then
        # we would need to reload the order...so lets just reload the history list
        $entries = $this->repoHistory->findBy([
            'orderId' => $order->getId()
        ]);

        /** @var History|false $lastEntry */
        $lastEntry = null;

        /** @var History $entry */
        foreach ($entries as $entry) {
            # search the one with the highest number
            # that is our "latest" one
            if ($lastEntry === null || $entry->getId() > $lastEntry->getId()) {
                $lastEntry = $entry;
            }
        }

        if (!$lastEntry instanceof History) {
            return;
        }

        # do not update previous comments
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
        $converter = new PaymentStatusConverter($this->config->getAuthorizedPaymentStatusId());

        $newStatusData = $converter->getShopwarePaymentStatus($status);

        $newShopwareStatus = $newStatusData->getTargetStatus();
        $ignoreState = $newStatusData->isIgnoreState();


        $previousShopwareStatus = $newShopwareStatus;

        # send a filter event, so developer can adjust the status that will
        # be used for the shopware payment status
        $newShopwareStatus = $this->eventManager->filter(
            Events::UPDATE_ORDER_PAYMENT_STATUS,
            $newShopwareStatus,
            [
                'molliePaymentStatus' => $status,
                'order' => $order,
            ]
        );

        if ($previousShopwareStatus !== $newShopwareStatus) {
            $this->logger->info(
                'Filter Event changed Payment Status for Order ' . $order->getNumber(),
                [
                    'data' => [
                        'previousStatus' => $previousShopwareStatus,
                        'newStatus' => $newShopwareStatus
                    ]
                ]
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

        try {

            $this->sOrder->setPaymentStatus(
                $order->getId(),
                $newShopwareStatus,
                $sendMail
            );

        } catch (\Zend_Mail_Transport_Exception $ex) {
            # never ever break if only an email cannot be sent
            # lets just add a log here.
            $this->logger->warning(
                'Problem when sending payment status update email for order: ' . $order->getNumber(),
                [
                    'error' => $ex->getMessage()
                ]
            );
        } catch (\Zend_Mail_Protocol_Exception $ex) {
            # never ever break if only an email cannot be sent
            # lets just add a log here.
            $this->logger->warning(
                'Problem when sending payment status update email for order: ' . $order->getNumber(),
                [
                    'error' => $ex->getMessage()
                ]
            );
        }

        return true;
    }

    /**
     * @param Order $order
     * @param $mollieStatus
     * @param $sendMail
     * @return bool
     * @throws OrderStatusNotFoundException
     * @throws \Enlight_Event_Exception
     */
    private function updateOrderStatus(Order $order, $mollieStatus, $sendMail)
    {
        $converter = new OrderStatusConverter($this->config->getShippedStatus());

        $newStatusData = $converter->getShopwareOrderStatus($mollieStatus);

        $newShopwareStatus = $newStatusData->getTargetStatus();
        $ignoreState = $newStatusData->isIgnoreState();

        $previousShopwareStatus = $newShopwareStatus;

        # send a filter event, so developer can adjust the status that will
        # be used for the shopware order status
        $newShopwareStatus = $this->eventManager->filter(
            Events::UPDATE_ORDER_STATUS,
            $newShopwareStatus,
            [
                'mollieOrderStatus' => $mollieStatus,
                'order' => $order,
            ]
        );

        if ($previousShopwareStatus !== $newShopwareStatus) {
            $this->logger->info(
                'Filter Event changed Order Status for Order ' . $order->getNumber(),
                [
                    'data' => [
                        'previousStatus' => $previousShopwareStatus,
                        'newStatus' => $newShopwareStatus
                    ]
                ]
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


        try {

            $this->sOrder->setOrderStatus(
                $order->getId(),
                $newShopwareStatus,
                $sendMail
            );

        } catch (\Zend_Mail_Transport_Exception $ex) {
            # never ever break if only an email cannot be sent
            # lets just add a log here.
            $this->logger->warning(
                'Problem when sending order status update email for order: ' . $order->getNumber(),
                [
                    'error' => $ex->getMessage()
                ]
            );
        } catch (\Zend_Mail_Protocol_Exception $ex) {
            # never ever break if only an email cannot be sent
            # lets just add a log here.
            $this->logger->warning(
                'Problem when sending order status update email for order: ' . $order->getNumber(),
                [
                    'error' => $ex->getMessage()
                ]
            );
        }

        return true;
    }
}
