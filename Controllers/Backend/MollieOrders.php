<?php

	// Mollie Shopware Plugin Version: 1.3.4

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\OrderLines;

class Shopware_Controllers_Backend_MollieOrders extends Shopware_Controllers_Backend_Application
{
    protected $model = 'Mollie\Models\MollieOrder';
    protected $alias = 'mollie_order';

    public function refundAction()
    {
        $transaction = null;

        try {
            $request = $this->Request();
            $em = $this->container->get('models');
            $config = $this->container->get('mollie_shopware.config');

            $orderId = $request->getParam('orderId');

            $transactionRepo = $em->getRepository(Transaction::class);
            $transaction = $transactionRepo->findOneBy([
                'order_id' => $orderId
            ]);

            $orderService = $this->container->get('mollie_shopware.order_service');
            $order = $orderService->getOrderById($orderId);
            $mollieId = $orderService->getMollieOrderId($order);

            if (empty($order)) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order not found',
                ]);
            }

            if (empty($mollieId)) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order doesn\'t seem to be paid through Mollie',
                ]);
            }

            // get an instance of the Mollie api
            $mollieApi = $this->container->get('mollie_shopware.api');

            // get an order object from mollie
            $mollieOrder = $mollieApi->orders->get($mollieId);

            // get shipment lines
            $mollieOrderDetailRepo = $em->getRepository(OrderLines::class);
            $mollieShipmentLines = $mollieOrderDetailRepo->getShipmentLines($order);

            // refund the payment
            $refund = $mollieApi->orderRefunds->createFor($mollieOrder, [
                'lines' => $mollieShipmentLines
            ]);

            // get refund status model
            $paymentStatusRefunded = $em->find('Shopware\Models\Order\Status', PaymentStatus::REFUNDED);

            // update order status
            $order->setPaymentStatus($paymentStatusRefunded);
            $em->persist($order);
            $em->flush();

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
            $this->returnJson([
                'success' => false,
                'message' => $ex->getMessage(),
            ]);
        }
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
