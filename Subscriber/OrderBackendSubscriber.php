<?php

	// Mollie Shopware Plugin Version: 1.3.12

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Logger;
use MollieShopware\Components\Services\OrderService;
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
                /** @var TransactionRepository $transactionRepo */
                $transactionRepo = Shopware()->Models()->getRepository(Transaction::class);

                /** @var Transaction $transaction */
                $transaction = $transactionRepo->findOneBy([
                    'transactionId' => $order->getTransactionId()
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

    public function onOrderPostDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        //@todo: throw better exceptions

        // only work on save action
        if ($args->getRequest()->getActionName() != 'save')
            return true;

        // vars
        $orderId = null;
        $order = null;
        $mollieId = null;

        try {
            $orderId = $args->getRequest()->getParam('id');
        }
        catch (Exception $ex) {
            // send exception
            $this->sendException(
                'HTTP/1.1 422 Unprocessable Entity Error',
                $ex->getMessage()
            );
        }

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