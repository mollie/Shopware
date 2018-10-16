<?php

    // Mollie Shopware Plugin Version: 1.3.2

use MollieShopware\Components\Constants\PaymentStatus;
//use MollieShopware\Models\Transaction;
//use MollieShopware\Models\OrderLines;

class Shopware_Controllers_Backend_MollieOrders extends Shopware_Controllers_Backend_Application
{
    protected $model = 'Mollie\Models\MollieOrder';
    protected $alias = 'mollie_order';

    public function refundAction()
    {
        $transaction = null;

        try {
            // vars
            $request = $this->Request();
            $orderId = $request->getParam('orderId');

            // services
            $modelManager = $this->container->get('models');
            $orderService = $this->container->get('mollie_shopware.order_service');
            $mollieApi = $this->container->get('mollie_shopware.api');
            $config = $this->container->get('mollie_shopware.config');

            // get order
            $order = $orderService->getOrderById($orderId);

            if (empty($order))
                $this->returnError('Order not found');

            // get transaction
            $transaction = $orderService->getTransaction($order);

            if (empty($transaction))
                $this->returnError('Mollie transaction not found');

            // get an order object from mollie
            $mollieOrder = $mollieApi->orders->get($transaction->getMollieId());

            // refund the whole order
            $refund = $mollieOrder->refundAll();

            // get refund status model
            $paymentStatusRefunded = $modelManager->find('Shopware\Models\Order\Status', PaymentStatus::REFUNDED);

            // update order status
            $order->setPaymentStatus($paymentStatusRefunded);
            $modelManager->persist($order);
            $modelManager->flush();

            // send status mail
            if ($config->sendStatusMail() && $config->sendRefundStatusMail()) {
                $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, PaymentStatus::REFUNDED);

                if ($mail) {
                    Shopware()->Modules()->Order()->sendStatusMail($mail);
                }
            }

            $this->returnJson([
                'success' => true,
                'message' => 'Order successfully refunded',
                'data' => $refund
            ]);
        } catch (Exception $ex) {
            $this->returnError($ex->getMessage());
        }
    }

    public function partialRefundAction()
    {
        $refund = null;

        try {
            // vars
            $request = $this->Request();
            $orderId = $request->getParam('orderId');

            // services
            $modelManager = $this->container->get('models');
            $orderService = $this->container->get('mollie_shopware.order_service');
            $mollieApi = $this->container->get('mollie_shopware.api');

            // get the order
            $order = $orderService->getOrderById($orderId);

            if (empty($order))
                $this->returnError('Order not found');

            // get the order lines
            $orderLine = $request->getParam('orderLine');

            if (empty($orderLine))
                $this->returnError('No order line selected for refund');

            // get the order lines
            $orderLineQuantity = $request->getParam('orderLineQuantity');

            if (empty($orderLineQuantity))
                $this->returnError('No order line selected for refund');

            // get transaction
            $transaction = $orderService->getTransaction($order);

            if (empty($transaction))
                $this->returnError('Mollie transaction not found');

            // get mollie order
            $mollieOrder = $mollieApi->orders->get($transaction->getMollieId());

            if (!empty($mollieOrder)) {
                $refund = $mollieOrder->refund([
                    'lines' => [
                        [
                            'id' => $orderLine,
                            'quantity' => $orderLineQuantity
                        ]
                    ]
                ]);
            }

            // set payment status
            if (!empty($refund)) {
                // get refund status model
                $paymentStatusRefunded = $modelManager
                    ->find('Shopware\Models\Order\Status', PaymentStatus::REFUNDED);

                // update order status
                $order->setPaymentStatus($paymentStatusRefunded);
                $modelManager->persist($order);
                $modelManager->flush();
            }
        }
        catch (Exception $ex) {
            $this->returnError($ex->getMessage());
        }
    }

    public function listOrderlinesAction()
    {
        try {
            // vars
            $request = $this->Request();
            $orderId = $request->getParam('orderId');

            // services
            $orderService = $this->container->get('mollie_shopware.order_service');
            $mollieApi = $this->container->get('mollie_shopware.api');

            // get order
            $order = $orderService->getOrderById($orderId);

            if (empty($order))
                $this->returnError('Order not found');

            // get mollie id
            $transaction = $orderService->getTransaction($order);

            if (empty($transaction))
                $this->returnError('Mollie transaction not found');

            // get mollie order
            $mollieOrder = $mollieApi->orders->get($transaction->getMollieId());

            if (empty($mollieOrder))
                $this->returnError('Mollie order not found');

            // get order lines
            $mollieOrderLines = $orderService->getMollieOrderLines($mollieOrder);

            if (count($mollieOrderLines)) {
                $this->returnJson([
                    'success' => true,
                    'message' => 'Order lines successfully retrieved',
                    'data' => $mollieOrderLines
                ]);
            }
            else {
                $this->returnError('Order lines not found');
            }
        }
        catch (Exception $ex) {
            $this->returnError($ex->getMessage());
        }
    }

    private function returnError($message) {
        $this->returnJson([
            'success' => false,
            'message' => $message,
        ]);
    }

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
