<?php

	// Mollie Shopware Plugin Version: 1.3.13

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Logger;
use MollieShopware\Components\Mollie\OrderService;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
use Exception;

class OrderBackendSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
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
                $order->getPaymentStatus()->getId() == PaymentStatus::OPEN) {
                /** @var TransactionRepository $transactionRepo */
                $transactionRepo = Shopware()->Models()->getRepository(Transaction::class);

                /** @var Transaction $transaction */
                $transaction = $transactionRepo->findOneBy([
                    'transaction_id' => $order->getTransactionId()
                ]);

                if (!empty($transaction) && empty($transaction->getOrdermailVariables())) {
                    try {
                        $transaction->setOrdermailVariables(json_encode($variables));
                        $transactionRepo->save($transaction);
                    }
                    catch (Exception $ex) {
                        Logger::log('error', $ex->getMessage(), $ex);
                    }

                    return false;
                }
            }
        }
    }

    private function sendException($type, $error)
    {
        header($type);
        header('Content-Type: text/html');
        die($error);
    }
}