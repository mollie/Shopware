<?php

namespace MollieShopware\Components\Services;

use MollieShopware\Components\Config;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\History;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
use Shopware\Models\User\User;

class OrderHistoryService
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ModelManager
     */
    private $modelManager;

    public function __construct(
        Config $config,
        ModelManager $modelManager
    )
    {
        $this->config = $config;
        $this->modelManager = $modelManager;
    }

    /**
     *
     * @param Order $order
     * @param int   $orderStatusId
     * @param int   $previousOrderStatusId
     * @param int   $paymentStatusId
     * @param int   $previousPaymentStatusId
     */
    public function addOrderHistory(
        Order $order,
        $orderStatusId,
        $previousOrderStatusId,
        $paymentStatusId,
        $previousPaymentStatusId
    )
    {
        // Create a new history object
        $history = new History();
        $history->setOrder($order);
        $history->setOrderStatus($this->getStatusById($orderStatusId));
        $history->setPreviousOrderStatus($this->getStatusById($previousOrderStatusId));
        $history->setPaymentStatus($this->getStatusById($paymentStatusId));
        $history->setPreviousPaymentStatus($this->getStatusById($previousPaymentStatusId));
        $history->setChangeDate(new \DateTime('now'));
        $history->setComment('Status updated by Mollie');

        try {
            $this->modelManager->persist($history);
            $this->modelManager->flush($history);
        } catch (\Exception $e) {
            //
        }
    }

    /**
     * @param $userId
     *
     * @return User|object|null
     */
    private function getUserById($userId)
    {
        $userRepository = $this->modelManager->getRepository(User::class);

        if ($userRepository === null) {
            return null;
        }

        return $userRepository->find($userId);
    }

    /**
     * @param $statusId
     *
     * @return Status|object|null
     */
    private function getStatusById($statusId)
    {
        $statusRepository = $this->modelManager->getRepository(Status::class);

        if ($statusRepository === null) {
            return null;
        }

        return $statusRepository->find($statusId);
    }
}