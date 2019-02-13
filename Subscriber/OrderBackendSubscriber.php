<?php

// Mollie Shopware Plugin Version: 1.4

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Logger;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class OrderBackendSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
            'Shopware_Modules_Order_SendMail_Send' => 'onSendMail',
        ];
    }

    public function onSendMail(\Enlight_Event_EventArgs $args)
    {
        $variables = $args->get('variables');
        $orderNumber = (isset($variables['ordernumber']) ? $variables['ordernumber'] : null);
        $order = null;
        $mollieOrder = null;

        if (!empty($orderNumber)) {
            /** @var OrderService $orderService */
            $orderService = Shopware()->Container()->get('mollie_shopware.order_service');

            /** @var Order $order */
            $order = $orderService->getOrderByNumber($orderNumber);
        }

        if (!empty($order)) {
            if (strstr($order->getTransactionId(), 'mollie_') &&
                $order->getPaymentStatus()->getId() == Status::PAYMENT_STATE_OPEN) {
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
}