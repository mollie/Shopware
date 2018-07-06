<?php

	// Mollie Shopware Plugin Version: 1.2.2

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Models\Transaction;

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
            $transaction = $transactionRepo->getByOrderNumber($orderId);

            $order = $em->find('Shopware\Models\Order\Order', $orderId);

            if (empty($order)) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order not found',
                ]);
            }

            $paymentMethod = $order->getPayment();

            // check if order is a Mollie order
            // Mollie payment ids begin with 'tr_'
            // Mollie payment methods are prefixed with 'mollie_' in Shopware
            if (substr($order->getTransactionId(), 0, 3) !== 'tr_' || stripos($paymentMethod->getName(), 'mollie_') === false) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order is not a Mollie order',
                ]);
            }

            // get Mollie payment ID from the order
            $paymentId = $order->getTransactionId();

            // get an instance of the Mollie api
            $mollie = $this->container->get('mollie_shopware.api');

            // Retrieve the payment to refund from the API.
            $payment = $mollie->payments->get($paymentId);

            // Check if this payment can be refunded
            // You can also check if the payment can be partially refunded
            // by using $payment->canBePartiallyRefunded() and $payment->getAmountRemaining()
            if (!$payment->canBeRefunded()) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order payment cannot be refunded',
                ]);
            }

            // Refund the payment.
            $refund = $mollie->payments->refund($payment);

            // get refund status model
            $paymentStatusRefunded = $em->find('Shopware\Models\Order\Status', PaymentStatus::REFUNDED);

            // update order status
            $order->setPaymentStatus($paymentStatusRefunded);
            $em->persist($order);
            $em->flush();

            if (!empty($transaction)) {
                $transactionRepo->updateStatus($transaction, PaymentStatus::REFUNDED);
            }

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
            if (!empty($transaction)) {
                $transactionRepo->addException($transaction, $ex);
            }

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
