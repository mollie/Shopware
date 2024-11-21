<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use MollieShopware\Components\Services\StockService;
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
            'Shopware_Modules_Order_SaveOrder_ProcessDetails' => 'resetStocks',
            'sOrder::setPaymentStatus::after' => 'increaseStocks'
        ];
    }

    public function resetStocks(Enlight_Event_EventArgs $eventArgs)
    {
        $orderId = (int)$eventArgs->get('orderId');
        $this->stockService->updateOrderStocks($orderId);
    }

    public function increaseStocks(Enlight_Hook_HookArgs $eventArgs)
    {
        $orderId = (int)$eventArgs->get('orderId');
        $paymentStatusId = (int)$eventArgs->get('paymentStatusId');
        if ($paymentStatusId !== Status::PAYMENT_STATE_COMPLETELY_PAID) {
            return;
        }

        $this->stockService->updateOrderStocks($orderId, false);
    }
}
