<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_Request;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use Exception;
use MollieShopware\Components\Config;
use MollieShopware\Components\Helpers\LogHelper;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Logger;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class OrderBackendSubscriber implements SubscriberInterface
{
    /** @var OrderService */
    private $orderService;

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
            'Shopware_Controllers_Api_Orders::putAction::after' => 'onOrderApiPut',
            'Shopware_Modules_Order_SendMail_Send' => 'onSendMail'
        ];
    }

    /**
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Catch mail variables when the confirmation email is triggered and store
     * them in the database to use them when the order is fully processed.
     *
     * @param Enlight_Event_EventArgs $args
     * @return bool
     * @throws Exception
     */
    public function onSendMail(Enlight_Event_EventArgs $args)
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
                $transactionRepo = Shopware()->Models()->getRepository(
                    Transaction::class
                );

                /** @var Transaction $transaction */
                $transaction = $transactionRepo->findOneBy([
                    'transactionId' => $order->getTransactionId()
                ]);

                if (!empty($transaction) && empty($transaction->getOrdermailVariables())) {
                    try {
                        $transaction->setOrdermailVariables(json_encode($variables));
                        $transactionRepo->save($transaction);
                    } catch (Exception $e) {
                        LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
                    }

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     * @return bool|void
     */
    public function onOrderPostDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        $request = $args->getRequest();

        if ($request === null) {
            return true;
        }

        if ($request->getActionName() === 'batchProcess') {
            return $this->processBatch($request);
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

    /**
     * @param Enlight_Controller_Request_Request $request
     * @return bool
     */
    private function processBatch(Enlight_Controller_Request_Request $request)
    {
        $orders = $request->getParam('orders');

        // if batch processing is used for a single order
        if ($orders === null) {
            $orderId = $request->getParam('id');

            if ($orderId === null) {
                return true;
            }

            return $this->shipOrderToMollie($orderId);
        }

        // if batch processing is used for multiple orders
        foreach ($orders as $order) {
            $orderId = array_key_exists('id', $order) ? $order['id'] : false;

            if (!$orderId) {
                continue;
            }

            $this->shipOrderToMollie($orderId);
        }

        return true;
    }

    /**
     * @param Enlight_Hook_HookArgs $args
     * @return bool
     * @throws Exception
     */
    public function onOrderApiPut(Enlight_Hook_HookArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var Enlight_Controller_Request_Request $request */
        $request = $controller->Request();

        if ($request === null) {
            return true;
        }

        /** @var Order $order */
        $order = null;

        /** @var int|string $orderId */
        $orderId = $request->getParam('id');

        $numberAsId = (bool)$request->getParam('useNumberAsId', 0);

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

    /**
     * @param $orderId
     * @return bool
     */
    private function shipOrderToMollie($orderId)
    {
        /** @var Order $order */
        $order = null;

        try {
            $order = $this->orderService->getOrderById($orderId);
        } catch (Exception $e) {
            //
        }

        if ($order === null) {
            return true;
        }

        $mollieId = null;

        try {
            $mollieId = $this->orderService->getMollieOrderId($order);
        } catch (Exception $e) {
            LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
        }

        if (empty($mollieId)) {
            return true;
        }

        # switch to the config of the shop from the order
        $shopSwitcher = new MollieShopSwitcher(Shopware()->Container());
        $subShopConfig = $shopSwitcher->getConfig($order->getShop()->getId());


        $orderStatusId = Status::ORDER_STATE_COMPLETELY_DELIVERED;

        if ($subShopConfig !== null) {
            $orderStatusId = $subShopConfig->getKlarnaShipOnStatus();
        }

        if ($order->getOrderStatus()->getId() !== $orderStatusId) {
            return true;
        }

        try {
            /** @var PaymentService $paymentService */
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

            # we have to do this after the payment
            # service...otherwise the reference is overwritten again
            $subShopApiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());
            # also do this again just to be sure
            $subShopConfig = $shopSwitcher->getConfig($order->getShop()->getId());


            # switch to the api client and config with
            # data for our sub shop
            $paymentService->switchConfig($subShopConfig);
            $paymentService->switchApiClient($subShopApiClient);

            $paymentService->sendOrder($mollieId);
        } catch (Exception $e) {
            LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
        }

        return true;
    }
}
