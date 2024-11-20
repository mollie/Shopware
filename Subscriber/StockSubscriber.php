<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Services\StockService;
use MollieShopware\Events\Events;
use Shopware\Models\Order\Order;

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
            Events::UPDATE_ORDER_PAYMENT_STATUS => 'increaseStocks'
        ];
    }

    public function resetStocks(Enlight_Event_EventArgs $eventArgs)
    {
        $orderId = (int)$eventArgs->get('orderId');
        $this->stockService->updateOrderStocks($orderId);
    }

    public function increaseStocks(Enlight_Event_EventArgs $eventArgs)
    {
        $paymentStatus = $eventArgs->get('molliePaymentStatus');
        $successStatus = [
            PaymentStatus::MOLLIE_PAYMENT_PAID,
            PaymentStatus::MOLLIE_PAYMENT_COMPLETED
        ];

        if (!in_array($paymentStatus, $successStatus)) {
            return;
        }
        /**
         * @var Order $order
         */
        $order = $eventArgs->get('order');

        $this->stockService->updateOrderStocks($order->getId(), false);
    }
}
