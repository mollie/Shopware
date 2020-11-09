<?php

use Shopware\Models\Order\Status;

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use Shopware\Models\Order\Detail;

class Shopware_Controllers_Backend_MollieOrders extends Shopware_Controllers_Backend_Application
{
    protected $model = \MollieShopware\Models\Transaction::class;
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

    public function shipAction()
    {
        try {
            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            /** @var \MollieShopware\Components\Config $config */
            $this->config = $this->container->get('mollie_shopware.config');
            
            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $this->orderService = $this->container->get('mollie_shopware.order_service');

            /** @var \MollieShopware\Components\Services\PaymentService $paymentService */
            $this->paymentService = $this->container->get('mollie_shopware.payment_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->orderService->getOrderById(
                $request->getParam('orderId')
            );

            if (empty($order))
                $this->returnError('Order not found');
            
            
            # switch to the config and api key of the shop from the order
            $shopSwitcher = new MollieShopSwitcher($this->container);
            $this->config = $shopSwitcher->getConfig($order->getShop()->getId());
            $this->apiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());

            
            $mollieId = $this->orderService->getMollieOrderId($order);

            if (empty($mollieId))
                $this->returnError('Order is only a transaction in Mollie. Transactions cannot be shipped, only payments that have created an order in Mollie can be shipped!');

            $mollieOrder = $this->apiClient->orders->get($mollieId);
            $errorMessage = '';

            if (empty($mollieOrder))
                $errorMessage = 'Could not find order at Mollie, are you sure it is paid through the Orders API?';
            if ($mollieOrder->isPending())
                $errorMessage = 'The order is pending at Mollie.';
            if ($mollieOrder->isExpired())
                $errorMessage = 'The order is expired at Mollie.';
            if ($mollieOrder->isCanceled())
                $errorMessage = 'The order is canceled at Mollie.';
            if ($mollieOrder->isShipping() || $mollieOrder->shipments()->count() > 0)
                $errorMessage = 'The order is already shipping at Mollie.';

            if ((string) $errorMessage !== '') {
                $this->returnError($errorMessage);
            }

            $result = $mollieOrder->shipAll();

            if ($result) {
                if ((int) $this->config->getShippedStatus() > 0) {
                    Shopware()->Modules()->Order()->setOrderStatus(
                        $order->getId(),
                        $this->config->getShippedStatus(),
                        $this->config->sendStatusMail()
                    );
                }

                $this->returnSuccess('Order status set to shipped at Mollie', true);
            } else {
                $this->returnError('Order status could not be set to shipped at Mollie');
            }
        }
        catch (\Exception $ex) {
            $this->returnError($ex->getMessage());
        }
    }

    public function refundAction()
    {
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

            if (empty($order))
                $this->returnError('Order not found');


            # switch to the config and api key of the shop from the order
            $shopSwitcher = new MollieShopSwitcher($this->container);
            $this->config = $shopSwitcher->getConfig($order->getShop()->getId());
            $this->apiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());
            
            
            /** @var Order $mollieOrder */
            try {
                $mollieOrder = $this->apiClient->orders->get(
                    $this->orderService->getMollieOrderId($order)
                );
            }
            catch (\Exception $ex) {
                //
            }

            $refund = null;

            if (!empty($mollieOrder)) {
                $refund = $this->refundOrder($order, $mollieOrder);
            }
            else {
                try {
                    $molliePayment = $this->apiClient->payments->get(
                        $this->orderService->getMolliePaymentId($order)
                    );
                }
                catch (\Exception $ex) {
                    //
                }

                if (!empty($molliePayment))
                    $refund = $this->refundPayment($order, $molliePayment);
            }

            if (!empty($refund))
                $this->returnSuccess('Order successfully refunded', $refund);
        }
        catch (\Exception $ex) {
            $this->returnError($ex->getMessage());
        }
    }

    public function partialRefundAction()
    {
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

            if (empty($order))
                $this->returnError('Order not found');

            # switch to the config and api key of the shop from the order
            $shopSwitcher = new MollieShopSwitcher($this->container);
            $this->config = $shopSwitcher->getConfig($order->getShop()->getId());
            $this->apiClient = $shopSwitcher->getMollieApi($order->getShop()->getId());
            
            /** @var Order $mollieOrder */
            try {
                $mollieOrder = $this->apiClient->orders->get(
                    $this->orderService->getMollieOrderId($order)
                );
            }
            catch (\Exception $ex) {
                //
            }

            $refund = null;

            if (!empty($mollieOrder)) {
                $orderLine = $mollieOrder->lines()->get($request->getParam('mollieOrderLineId'));

                if ($orderLine !== null) {
                    $refund = $this->partialRefundOrder(
                        $order,
                        $orderDetail,
                        $mollieOrder,
                        $orderLine,
                        (int) $request->get('quantity')
                    );
                }
            }

            if (!empty($refund))
                $this->returnSuccess('Order line successfully refunded', $refund);
        }
        catch (\Exception $ex) {
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

            if ($order !== null && (string) $this->orderService->getMollieOrderId($order) !== '') 
            {
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
     * Refund a Mollie order
     *
     * @param \Shopware\Models\Order\Order $order
     * @param Order $mollieOrder
     *
     * @throws \Exception
     *
     * @return bool|\Mollie\Api\Resources\Refund
     */
    private function refundOrder(\Shopware\Models\Order\Order $order, Order $mollieOrder)
    {
        if (empty($this->modelManager) || empty($this->apiClient))
            return false;

        /** @var \MollieShopware\Models\OrderLinesRepository $mollieOrderLinesRepo */
        $mollieOrderLinesRepo = $this->modelManager->getRepository(
            \MollieShopware\Models\OrderLines::class
        );

        $mollieShipmentLines = $mollieOrderLinesRepo->getShipmentLines($order);

        /** @var \Mollie\Api\Resources\Refund $refund */
        $refund = $this->apiClient->orderRefunds->createFor($mollieOrder, [
            'lines' => $mollieShipmentLines
        ]);

        if (!empty($refund)) {
            $this->processRefund($order);

            if ($order->getDetails()->isEmpty() === false) {
                foreach ($order->getDetails() as $detail) {
                    $this->updateRefundedItemsOnOrderDetail($detail, $detail->getQuantity());
                }
            }
        }

        return $refund;
    }

    /**
     * Refund a Mollie order
     *
     * @param \Shopware\Models\Order\Order $order
     * @param Order $mollieOrder
     *
     * @throws \Exception
     *
     * @return bool|\Mollie\Api\Resources\Refund
     */
    private function partialRefundOrder(
        \Shopware\Models\Order\Order $order,
        Detail $detail,
        Order $mollieOrder,
        OrderLine $orderLine,
        $quantity = 1
    )
    {
        if (empty($this->modelManager) || empty($this->apiClient))
            return false;

        $data = [
            'lines' => [
                [
                    'id' => $orderLine->id,
                    'quantity' => $quantity,
                ],
            ]
        ];

        /** @var \Mollie\Api\Resources\Refund $refund */
        $refund = $this->apiClient->orderRefunds->createFor($mollieOrder, $data);

        if (!empty($refund)) {
            $this->processRefund($order);
            $this->updateRefundedItemsOnOrderDetail($detail, $quantity);
        }

        return $refund;
    }

    /**
     * Refund a Mollie payment
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \Mollie\Api\Resources\Payment $molliePayment
     *
     * @return Mollie\Api\Resources\BaseResource
     *
     * @throws \Exception
     */
    private function refundPayment(\Shopware\Models\Order\Order $order, \Mollie\Api\Resources\Payment $molliePayment)
    {
        $refund = $molliePayment->refund([
            'amount' => [
                'currency' => $order->getCurrency(),
                'value' => number_format($order->getInvoiceAmount(), 2, '.', '')
            ]
        ]);

        if (!empty($refund))
            $this->processRefund($order);

        return $refund;
    }

    /**
     * Send the status e-mail
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function processRefund(\Shopware\Models\Order\Order $order)
    {
        if (empty($this->config) || empty($this->modelManager))
            return false;

        /** @var \Shopware\Models\Order\Repository $orderStatusRepo */
        $orderStatusRepo = $this->modelManager->getRepository(
            \Shopware\Models\Order\Status::class
        );

        /** @var \Shopware\Models\Order\Status $paymentStatusRefunded */
        $paymentStatusRefunded = $orderStatusRepo->find(
            \Shopware\Models\Order\Status::PAYMENT_STATE_RE_CREDITING
        );

        // set the payment status
        $order->setPaymentStatus($paymentStatusRefunded);

        // save the order
        $this->modelManager->persist($order);
        $this->modelManager->flush();

        // send status email
        if ($this->config->sendStatusMail() && $this->config->sendRefundStatusMail()) {
            $mail = Shopware()->Modules()->Order()->createStatusMail(
                $order->getId(),
                $paymentStatusRefunded->getId()
            );

            if ($mail)
                Shopware()->Modules()->Order()->sendStatusMail($mail);
        }

        return true;
    }

    private function updateRefundedItemsOnOrderDetail($detail, $quantity)
    {
        if (
            $detail->getAttribute() !== null
            && method_exists($detail->getAttribute(), 'getMollieReturn')
            && method_exists($detail->getAttribute(), 'setMollieReturn')
        ) {
            $mollieReturn = $detail->getAttribute()->getMollieReturn();
            $mollieReturn += $quantity;

            $detail->getAttribute()->setMollieReturn($mollieReturn);

            try {
                $this->modelManager->persist($detail->getAttribute());
                $this->modelManager->flush($detail->getAttribute());
            } catch (Exception $e) {
                //
            }
        }
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
        if ($httpCode !== 200)
            http_response_code(intval($httpCode));

        header('Content-Type: application/json');
        echo json_encode($data);

        exit;
    }
}
