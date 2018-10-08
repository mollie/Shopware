<?php

	// Mollie Shopware Plugin Version: 1.3.0

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Mollie\OrderService;
use MollieShopware\Models\OrderDetailMollieID;
use Shopware\Models\Order\Status;
use Exception;

class OrderBackendSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
        ];
    }

    public function onOrderPostDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        //@todo: throw better exceptions

        // only work on save action
        if ($args->getRequest()->getActionName() != 'save')
            return true;

        // vars
        $orderId = $args->getRequest()->getParam('id');
        $order = null;
        $mollieId = null;

        // check if we have an order
        if (empty($orderId))
            return false;

        // create order service
        $orderService = Shopware()->Container()
            ->get('mollie_shopware.order_service');

        try {
            // get the order
            $order = $orderService->getOrderById($orderId);
        }
        catch (Exception $ex) {
            // send exception
            $this->sendException(
                'HTTP/1.1 422 Unprocessable Entity Error',
                $ex->getMessage()
            );
        }

        // check if the order is found
        if (empty($order))
            return false;

        try {
            // get mollie id
            $mollieId = $orderService->getMollieOrderId($order);
        }
        catch (Exception $ex) {
            // send exception
            $this->sendException(
                'HTTP/1.1 422 Unprocessable Entity Error',
                $ex->getMessage()
            );
        }

        // if the order is not a mollie order, return true
        if (empty($mollieId))
            return true;

        // check if the status is sent
        if ($order->getOrderStatus()->getId() != Status::ORDER_STATE_COMPLETELY_DELIVERED)
            return false;

        // send the order to mollie
        try {
            // create a payment service
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

            // send the order
            $paymentService->sendOrder($order, $mollieId);
        }
        catch (Exception $ex) {
            // send exception
            $this->sendException(
                'HTTP/1.1 422 Unprocessable Entity Error',
                $ex->getMessage()
            );
        }
    }

    private function sendException($type, $error)
    {
        header($type);
        header('Content-Type: text/html');
        die($error);
    }
}