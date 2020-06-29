<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Config;
use MollieShopware\Components\Helpers\LogHelper;
use MollieShopware\Components\Logger;
use MollieShopware\Components\Services\OrderService;
use Shopware\Models\Order\Order;

class OrderBackendSubscriber implements SubscriberInterface
{
    /** @var OrderService */
    private $orderService;

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
            'Shopware_Controllers_Api_Orders::putAction::after' => 'onOrderApiPut',
            'Shopware_Modules_Order_SendMail_Send' => 'onSendMail'
        ];
    }

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
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
                    } catch (\Exception $e) {
                        LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
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

        if ($request === null) {
            return true;
        }

        if ($request->getActionName() !== 'save') {
            return true;
        }

        $orderId = $request->getParam('id');

        if (empty($orderId)) {
            return true;
        }

        return $this->shipOrderToMollie($orderId);
    }

    public function onOrderApiPut(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var \Enlight_Controller_Request_Request $request */
        $request = $controller->Request();

        if ($request === null) {
            return true;
        }

        /** @var Order $order */
        $order = null;

        /** @var int|string $orderId */
        $orderId = $request->getParam('id');

        /** @var bool $numberAsId */
        $numberAsId = (bool) $request->getParam('useNumberAsId', 0);

        if (empty($orderId)) {
            return true;
        }

        if ($numberAsId === true) {
            $order = $this->orderService->getOrderByNumber($orderId);
        }

        if ($order !== null) {
            $orderId = $order->getId();
        }

        return $this->shipOrderToMollie($orderId);
    }

    private function shipOrderToMollie($orderId)
    {
        /** @var \Shopware\Models\Order\Order $order */
        $order = null;

        try {
            $order = $this->orderService->getOrderById($orderId);
        } catch (\Exception $e) {
            //
        }

        if ($order === null) {
            return true;
        }

        $mollieId = null;

        try {
            $mollieId = $this->orderService->getMollieOrderId($order->getId());
        } catch (\Exception $e) {
            LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
        }

        if (empty($mollieId)) {
            return true;
        }

        /** @var Config $config */
        $config = Shopware()->Container()->get('mollie_shopware.config');

        $orderStatusId = \Shopware\Models\Order\Status::ORDER_STATE_COMPLETELY_DELIVERED;

        if ($config !== null) {
            $orderStatusId = $config->getKlarnaShipOnStatus();
        }

        if ($order->getOrderStatus()->getId() !== $orderStatusId) {
            return true;
        }

        try {
            /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
            $paymentService = Shopware()->Container()
                ->get('mollie_shopware.payment_service');

            $paymentService->sendOrder($mollieId);
        } catch (\Exception $e) {
            LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
        }
    }
}