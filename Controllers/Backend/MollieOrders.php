<?php

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Models\Transaction;
use MollieShopware\Services\Refund\RefundService;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Status;

class Shopware_Controllers_Backend_MollieOrders extends Shopware_Controllers_Backend_Application
{
    protected $model = Transaction::class;
    protected $alias = 'mollie_order';

    /** @var \MollieShopware\Components\Config $config */
    protected $config;

    /** @var \Shopware\Components\Model\ModelManager $modelManager */
    protected $modelManager;

    /** @var \Mollie\Api\MollieApiClient $apiClient */
    protected $apiClient;

    /** @var \MollieShopware\Components\Services\OrderService $orderService */
    protected $orderService;

    /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
    protected $paymentService;

    /** @var RefundService */
    protected $refundService;

    public function preDispatch()
    {
        $this->refundService = $this->container->get('mollie_shopware.services.refund_service');
        parent::preDispatch();
    }


    /**
     *
     */
    public function shipAction()
    {

        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $this->container->get('mollie_shopware.components.logger');

        try {
            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            /** @var \MollieShopware\Components\Config $config */
            $this->config = $this->container->get('mollie_shopware.config');

            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $this->orderService = $this->container->get('mollie_shopware.order_service');

            /** @var \Shopware\Components\Model\ModelManager $modelManager */
            $this->modelManager = $this->container->get('models');

            /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
            $this->paymentService = $this->container->get('mollie_shopware.payment_service');

            /** @var \MollieShopware\Gateways\Mollie\MollieGatewayFactory $gwMollie */
            $gwMollieFactory = $this->container->get('mollie_shopware.gateways.mollie.factory');


            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->orderService->getOrderById(
                $request->getParam('orderId')
            );

            if ($order === null) {
                $this->returnError('Order not found');
            }

            # switch to the config and api key of the shop from the order
            $shopSwitcher = new MollieShopSwitcher($this->container);
            $this->config = $shopSwitcher->getConfig($order->getShop()->getId());
            $this->apiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());

            /** @var \MollieShopware\Gateways\MollieGatewayInterface $gwMollie */
            $gwMollie = $gwMollieFactory->create($this->apiClient);


            $mollieId = $this->orderService->getMollieOrderId($order);

            if (empty($mollieId)) {
                $this->returnError('Order is only a transaction in Mollie. Transactions cannot be shipped, only payments that have created an order in Mollie can be shipped!');
            }

            $mollieOrder = $this->apiClient->orders->get($mollieId);
            $errorMessage = '';

            if ($mollieOrder === null) {
                $errorMessage = 'Could not find order at Mollie, are you sure it is paid through the Orders API?';
            }
            if ($mollieOrder->isPending()) {
                $errorMessage = 'The order is pending at Mollie.';
            }
            if ($mollieOrder->isExpired()) {
                $errorMessage = 'The order is expired at Mollie.';
            }
            if ($mollieOrder->isCanceled()) {
                $errorMessage = 'The order is canceled at Mollie.';
            }
            if ($mollieOrder->isShipping() || $mollieOrder->shipments()->count() > 0) {
                $errorMessage = 'The order is already shipping at Mollie.';

                # TODO, should we think of "repairing" the local database entry and mark it also here as "shipped"?
            }

            if ((string)$errorMessage !== '') {
                $this->returnError($errorMessage);
            }

            $mollieShipping = new MollieShipping($gwMollie);

            $result = $mollieShipping->shipOrder($order, $mollieOrder);

            if ($result) {
                /** @var Transaction|null $transaction */
                $transaction = $this->modelManager->getRepository(Transaction::class)->findOneBy(
                    ['mollieId' => $mollieOrder->id]
                );

                if ($transaction === null) {
                    $this->returnError('no transaction found for current order');
                    return;
                }

                $transaction->setIsShipped(true);

                if ((int)$this->config->getShippedStatus() > 0) {
                    Shopware()->Modules()->Order()->setOrderStatus(
                        $order->getId(),
                        $this->config->getShippedStatus(),
                        $this->config->isPaymentStatusMailEnabled()
                    );
                }

                $this->modelManager->flush($transaction);

                $this->returnSuccess('Order status set to shipped at Mollie', true);
            } else {
                $this->returnError('Order status could not be set to shipped at Mollie');
            }
        } catch (\Exception $ex) {

            $logger->error(
                'Error when starting shipping in Shopware Backend',
                [
                    'error' => $ex->getMessage(),
                ]
            );

            $this->returnError($ex->getMessage());
        }
    }

    /**
     *
     */
    public function refundAction()
    {
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $this->container->get('mollie_shopware.components.logger');

        try {

            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            /** @var \Shopware\Components\Model\ModelManager $modelManager */
            $this->modelManager = $this->container->get('models');

            /** @var \MollieShopware\Components\Config $config */
            $this->config = $this->container->get('mollie_shopware.config');

            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $this->orderService = $this->container->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->orderService->getOrderById($request->getParam('orderId'));

            if ($order === null) {
                $this->returnError('Order not found');
            }

            $logger->info('Starting full refund in Backend for Order: ' . $order->getNumber());

            # switch to the config and api key of the shop from the order
            $shopSwitcher = new MollieShopSwitcher($this->container);
            $this->config = $shopSwitcher->getConfig($order->getShop()->getId());
            $this->apiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());

            $transaction = $this->orderService->getOrderTransactionByNumber($order->getNumber());

            $refund = $this->refundService->refundFullOrder($order, $transaction);

            $logger->info('Full refund successful in Backend for Order: ' . $order->getNumber());

            $this->returnSuccess('Order successfully refunded', $refund);
        } catch (\Exception $ex) {
            $logger->error(
                'Error when executing a full refund order in Shopware Backend',
                [
                    'error' => $ex->getMessage(),
                ]
            );

            $this->returnError($ex->getMessage());
        }
    }


    /**
     *
     */
    public function partialRefundAction()
    {

        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $this->container->get('mollie_shopware.components.logger');


        try {

            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            /** @var \Shopware\Components\Model\ModelManager $modelManager */
            $this->modelManager = $this->container->get('models');

            /** @var \MollieShopware\Components\Config $config */
            $this->config = $this->container->get('mollie_shopware.config');

            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $this->orderService = $this->container->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->orderService->getOrderById(
                $request->getParam('orderId')
            );

            /** @var Detail $orderDetail */
            $orderDetail = $this->orderService->getOrderDetailById(
                $request->getParam('orderDetailId')
            );

            if ($order === null) {
                $this->returnError('Order not found');
            }

            $logger->info('Starting partial refund in Backend for Order: ' . $order->getNumber());

            # switch to the config and api key of the shop from the order
            $shopSwitcher = new MollieShopSwitcher($this->container);
            $this->config = $shopSwitcher->getConfig($order->getShop()->getId());
            $this->apiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());

            $transaction = $this->orderService->getOrderTransactionByNumber($order->getNumber());
            $orderLineID = $request->getParam('mollieOrderLineId');
            $quantity = (int)$request->get('quantity');

            $refund = $this->refundService->refundPartialOrderItem(
                $order,
                $orderDetail,
                $transaction,
                $orderLineID,
                $quantity
            );

            $logger->info('Partial refund successful in Backend for Order: ' . $order->getNumber());

            $this->returnSuccess('Order line successfully refunded', $refund);
        } catch (\Exception $ex) {
            $logger->error(
                'Error when executing a partial refund order in Shopware Backend',
                [
                    'error' => $ex->getMessage(),
                ]
            );

            $this->returnError($ex->getMessage());
        }
    }

    public function shippableAction()
    {
        $shippable = false;

        try {
            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            /** @var \Shopware\Components\Model\ModelManager $modelManager */
            $this->modelManager = $this->container->get('models');

            /** @var \MollieShopware\Components\Config $config */
            $this->config = $this->container->get('mollie_shopware.config');

            /** @var \Mollie\Api\MollieApiClient $apiClient */
            $this->apiClient = $this->container->get('mollie_shopware.api');

            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $this->orderService = $this->container->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->orderService->getOrderById(
                $request->getParam('orderId')
            );

            if ($order !== null && (string)$this->orderService->getMollieOrderId($order) !== '') {
                $shippable = true;
            }
        } catch (Exception $e) {
            //
        }

        $this->returnJson([
            'shippable' => $shippable,
        ]);
    }

    /**
     * Return success JSON
     *
     * @param $message
     * @param $data
     */
    protected function returnSuccess($message, $data)
    {
        $this->returnJson([
            'success' => true,
            'message' => addslashes($message),
            'data' => $data
        ]);
    }

    /**
     * Return success JSON
     *
     * @param $message
     */
    protected function returnError($message)
    {
        $this->returnJson([
            'success' => false,
            'message' => addslashes($message),
        ]);
    }

    /**
     * Return JSON
     *
     * @param $data
     * @param int $httpCode
     */
    protected function returnJson($data, $httpCode = 200)
    {
        if ($httpCode !== 200) {
            http_response_code(intval($httpCode));
        }

        header('Content-Type: application/json');
        echo json_encode($data);

        exit;
    }
}
