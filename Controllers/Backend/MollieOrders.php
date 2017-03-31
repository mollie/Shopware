<?php

use Shopware_Controllers_Backend_Application;

class Shopware_Controllers_Backend_MollieOrders extends Shopware_Controllers_Backend_Application
{
    protected $model = 'Mollie\Models\MollieOrder';
    protected $alias = 'mollie_order';

	const PAYMENTSTATUS_REFUNDED = 20;

	public function refundAction()
	{
		try
		{
			$request = $this->Request();
			$em = $this->container->get('models');

			$orderId = $request->getParam('orderId');

			$order = $em->find('Shopware\Models\Order\Order', $orderId);

			if (empty($order))
			{
				$this->returnJson([
					'success' => false,
					'message' => 'Order not found',
				]);
			}

			$paymentMethod = $order->getPayment();

			// check if order is a Mollie order
			// Mollie payment ids begin with 'tr_'
			// Mollie payment methods are prefixed with 'mollie_' in Shopware
			if (substr($order->getTransactionId(), 0, 3) !== 'tr_' || stripos($paymentMethod->getName(), 'mollie_') === false)
			{
				$this->returnJson([
					'success' => false,
					'message' => 'Order is not a Mollie order',
				]);
			}

			// get Mollie payment ID from the order
			$paymentId = $order->getTransactionId();

			// get an instance of the Mollie api
			$mollie = $this->getMollieClient();

			// Retrieve the payment to refund from the API.
			$payment = $mollie->payments->get($paymentId);

			// Check if this payment can be refunded
			// You can also check if the payment can be partially refunded
			// by using $payment->canBePartiallyRefunded() and $payment->getAmountRemaining()
			if (!$payment->canBeRefunded())
			{
				$this->returnJson([
					'success' => false,
					'message' => 'Order payment cannot be refunded',
				]);
			}

			// Refund the payment.
			$refund = $mollie->payments->refund($payment);

			// get refund status model
			$paymentStatusRefunded = $em->find('Shopware\Models\Order\Status', static::PAYMENTSTATUS_REFUNDED);

			// update order status
			$order->setPaymentStatus($paymentStatusRefunded);
			$em->persist($order);
			$em->flush();

			$this->returnJson([
				'success' => true,
				'message' => 'Order successfully refunded',
				'data' => $refund
			]);
		}
		catch(Exception $ex)
		{
			$this->returnJson([
				'success' => false,
				'message' => $ex->getMessage(),
			]);
		}
	}

    protected function getMollieClient()
    {
		$apiKey = Shopware()->Config()->getByNamespace('Mollie', 'api-key');

		$mollie = new Mollie_API_Client;
		$mollie->setApiKey($apiKey);

		return $mollie;
    }

	protected function returnJson($data, $httpCode = 200)
	{
		if ($httpCode !== 200)
		{
			http_response_code(intval($httpCode));
		}

		header('Content-Type: application/json');

		echo json_encode($data);

		exit;
	}
}
