<?php

// Mollie Shopware Plugin Version: 1.4.2

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Logger;

class OrderBackendSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
            'Shopware_Modules_Order_SendMail_Send' => 'onSendMail'
        ];
    }

    /**
     * Catch mailvariables when the confirmation email is triggered and store
     * them in the database to use them when the order is fully processed.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return bool
     * @throws \Exception
     */
    public function onSendMail(\Enlight_Event_EventArgs $args)
    {
        $variables = $args->get('variables');
        $orderNumber = (isset($variables['ordernumber']) ? $variables['ordernumber'] : null);
        $order = null;
        $mollieOrder = null;

        if (!empty($orderNumber)) {
            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderService->getOrderByNumber($orderNumber);
        }

        if (!empty($order)) {
            if (strstr($order->getTransactionId(), 'mollie_') &&
                $order->getPaymentStatus()->getId() == \Shopware\Models\Order\Status::PAYMENT_STATE_OPEN) {

                /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
                $transactionRepo = Shopware()->Models()->getRepository(
                    \MollieShopware\Models\Transaction::class
                );

                /** @var Transaction $transaction */
                $transaction = $transactionRepo->findOneBy([
                    'transactionId' => $order->getTransactionId()
                ]);

                if (!empty($transaction) && empty($transaction->getOrdermailVariables())) {
                    try {
                        $transaction->setOrdermailVariables(json_encode($variables));
                        $transactionRepo->save($transaction);
                    }
                    catch (\Exception $ex) {
                        Logger::log('error', $ex->getMessage(), $ex);
                    }

                    return false;
                }
            }
        }
    }

    public function onOrderPostDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Enlight_Controller_Request_Request $request */
        $request = $args->getRequest();

        if ($request == null)
            return true;

        if ($request->getActionName() != 'save')
            return true;

        $orderId = $request->getParam('id');

        if (empty($orderId))
            return true;

        $order = null;

        try {
            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $orderService = Shopware()->Container()
                ->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderService->getOrderById($orderId);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        if (empty($order))
            return true;

        $mollieId = null;

        try {
            $mollieId = $orderService->getMollieOrderId($order);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        if (empty($mollieId))
            return true;

        if ($order->getOrderStatus()->getId() != \Shopware\Models\Order\Status::ORDER_STATE_COMPLETELY_DELIVERED)
            return true;

        try {
            /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
            $paymentService = Shopware()->Container()
                ->get('mollie_shopware.payment_service');

            $paymentService->sendOrder($mollieId);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }
    }
}