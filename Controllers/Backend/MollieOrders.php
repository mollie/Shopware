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

            $currency = $request->getParam('currency');

            $amount = $request->getParam('amount');
            $amount = preg_replace('/[^\,\.0-9]+/', '', $amount);

            $items = preg_split('/[^0-9]/', $amount);
            if (count($items) == 1){
                $amount = $items[0] * 1;
            }
            else{
                $amount = '';
                foreach($items as $index=>$item){

                    if ($index === count($items) - 1){
                        if (strlen($item) === 3){
                            // separator was a thousand separator
                            $amount .= $item;
                        }
                        else{
                            // separator was a decimal separator
                            $amount .= '.' . $item;
                        }
                    }
                    else{
                        $amount .= $item;
                    }

                }
            }


            $orderNumber = $request->getParam('orderNumber');
            $orderId = $request->getParam('orderId');

            $transactionRepo = $em->getRepository(Transaction::class);
            $transaction = $transactionRepo->getByOrderNumber($orderNumber);

            $order = $em->find('Shopware\Models\Order\Order', $orderId);

            if (empty($order)) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order not found',
                ]);
            }

            $paymentMethod = $order->getPayment();

            // get Mollie payment ID from the order
            $paymentId = $transaction->getTransactionId();


            // check if order is a Mollie order
            // Mollie payment ids begin with 'tr_'
            // Mollie payment methods are prefixed with 'mollie_' in Shopware
            if (substr($paymentId, 0, 3) !== 'tr_' || stripos($paymentMethod->getName(), 'mollie_') === false) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Order is not a Mollie order',
                ]);
            }



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
            $refund = $mollie->payments->refund($payment, ['amount'=>['value'=>number_format($amount, 2, '.', ''), 'currency'=>$currency]]);

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
