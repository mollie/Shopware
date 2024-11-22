<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use MollieShopware\Components\Services\StockService;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class StockSubscriber implements SubscriberInterface
{
    /**
     * @var StockService
     */
    private $stockService;

    public function __construct($stockService)
    {
        $this->stockService = $stockService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Order_SaveOrder_OrderCreated' => 'resetStocks',
            'sOrder::setPaymentStatus::after' => 'increaseStocks',
            'Shopware\Models\Order\Order::postUpdate' => 'entityEvent',
        ];
    }

    public function resetStocks(Enlight_Event_EventArgs $eventArgs)
    {
        $orderId = (int)$eventArgs->get('orderId');
        $this->stockService->updateOrderStocks($orderId);
    }

    public function increaseStocks(Enlight_Event_EventArgs $eventArgs)
    {
        $orderId = (int)$eventArgs->get('orderId');
        $paymentStatusId = (int)$eventArgs->get('paymentStatusId');

        if ($paymentStatusId !== Status::PAYMENT_STATE_COMPLETELY_PAID) {
            return;
        }

        $this->stockService->updateOrderStocks($orderId, false);
    }


    public function entityEvent(Enlight_Event_EventArgs $eventArgs)
    {
        /** @var Order $order */
        $order = $eventArgs->get('entity');
        $orderId = $order->getId();
        /** @var ModelManager $em */
        $em = $eventArgs->get('entityManager');
        $changes = $em->getUnitOfWork()->getEntityChangeSet($order);
        if (! isset($changes['paymentStatus'])) {
            return;
        }
        $paymentStatusChanges = $changes['paymentStatus'];

        /** @var Status $new */
        $new = array_pop($paymentStatusChanges);
        /** @var Status $old */
        $old = array_pop($paymentStatusChanges);

        $oldId = $old->getId();
        $newId = $new->getId();

        if ($oldId === $newId) {
            return;
        }
        if ($newId !== Status::PAYMENT_STATE_COMPLETELY_PAID) {
            return;
        }

        $this->stockService->updateOrderStocks($orderId, false);
    }
}
