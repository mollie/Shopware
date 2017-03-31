<?php

use Shopware\Components\CSRFWhitelistAware;
use Mollie\Components\RequestLogger;
/**
 * extends Shopware_Controllers_Frontend_Payment
 * https://github.com/shopware/shopware/blob/5.2/engine/Shopware/Controllers/Frontend/Payment.php
 */
class Shopware_Controllers_Frontend_Mollie extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
	const PAYMENTSTATUS_PAID = 12;
	const PAYMENTSTATUS_OPEN = 17;
	const PAYMENTSTATUS_CANCELLED = 35;
	const PAYMENTSTATUS_PARTIAL_PAID = 11;
	const PAYMENTSTATUS_REFUNDED = 20;

    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return [ 'notify' ];
    }

    /**
     * Index action method.
     *
     * Is called after customer clicks the 'Confirm Order' button
     *
     * Forwards to the correct action.
     */
	public function indexAction()
	{
		// only handle if it is a Mollie payment
		if (stripos($this->getPaymentShortName(), 'mollie_') !== false)
		{
			$this->redirect([ 'action' => 'direct', 'forceSecure' => true ]);
		}
		else
		{
			$this->redirect([ 'controller' => 'checkout' ]);
		}
	}

	public function directAction()
	{
		try
		{
			$log = new RequestLogger('direct', $this);
			$log->writeGlobals('trace');

			$mollie = $this->getMollieClient();

			$webhookUrl = $this->Front()->Router()->assemble([ 'controller' => 'Mollie', 'action' => 'notify', 'appendSession' => true ]);
			$returnUrl = $this->Front()->Router()->assemble([ 'controller' => 'Mollie', 'action' => 'return' ]);

			$paymentOptions = [
				'amount'       => $this->getAmount(),
				'description'  => $this->getPaymentShortName(), // TODO: give decent description
				'redirectUrl'  => $returnUrl . "?token=" . $this->createPaymentToken(),
				'webhookUrl'   => $webhookUrl,
				'method'       => str_replace('mollie_', '', $this->getPaymentShortName()),
				'metadata'     => [
					'token' => $this->createPaymentToken(),

					// https://developers.shopware.com/developers-guide/payment-plugin/#generate-signature
					'signature' => method_exists($this, 'persistBasket') ? $this->persistBasket() : '',
				],
			];

			if (stripos($this->getPaymentShortName(), 'ideal') !== false) {
				$paymentOptions['issuer'] = $this->getIdealIssuer();
				$log->write("issuer: \n" . $this->getIdealIssuer(), 'debug');
			}

			$payment = $mollie->payments->create($paymentOptions);
			$log->write("Payment: \n" . print_r($payment, true), 'debug');

			// write payment id to session
			$this->setPaymentId($payment->id);

			// redirect customer to Mollie
			$this->redirect($payment->getPaymentUrl());
		}
		catch(Mollie_API_Exception $ex)
		{
			echo "Mollie exception: " . $ex->getMessage();
			$log->write('Mollie_API_Exception: ' . $ex->getMessage());
			exit;
			// TODO: - foutieve API key afhandelen
			//       - Betalingsmethode niet ondersteund afhandelen
		}
		catch(Exception $ex)
		{
			echo "Exception: " . $ex->getMessage();
			$log->write('Exception: ' . $ex->getMessage());
			exit;
		}
	}

	/**
	 * Webhook action method
	 *
	 * Called by Mollie when the payment has a new status
	 */
	public function notifyAction()
	{
		try
		{
			$log = new RequestLogger('notify', $this);
			$log->writeGlobals('debug');

			$request = $this->Request();

			/*
			 * Check if this is a test request by Mollie
			 */
			if ($request->getParam('testByMollie', null))
			{
				$log->write('testByMollie', 'info');
				exit('OK');
			}

			$mollie = $this->getMollieClient();

			/*
			 * Retrieve the payment's current state.
			 */
			$paymentId = $request->getParam('id', null);
			$log->write('PaymentId: ' . $paymentId, 'trace');

			$payment = $mollie->payments->get($paymentId);	
			$log->write("Payment:\n" . print_r($payment, true), 'debug');

			// check token matches
			$token = $payment->metadata->token;

			// check for refunded status
			if (strtolower($payment->status) === 'refunded') {
				$log->write('Payment status === refunded', 'trace');

				$log->write("saveOrder:\n" . print_r([
					'transactionId' => $paymentId,
					'paymentUniqueId' => $token, 
					'paymentStatusId' => 'PAYMENTSTATUS_REFUNDED ' . static::PAYMENTSTATUS_REFUNDED,
					'sendStatusMail' => true
				], true), 'debug');

	            $this->saveOrder(
	                $paymentId,
	                $token,
	                static::PAYMENTSTATUS_REFUNDED,
	                true // sendStatusMail
	            );

	            exit('OK');
			}

			if ($payment->isPaid())
			{
				$log->write('payment->isPaid == true', 'trace');

				/*
				 * At this point you'd probably want to start the process of delivering the product to the customer.
				 */

				// check if the whole amount is paid
				// $status = $payment->getAmountRemaining() <= 0 ? static::PAYMENTSTATUS_PAID : static::PAYMENTSTATUS_PARTIAL_PAID;
				// $log->write('Payment amount remaining: ' . $payment->getAmountRemaining(), 'debug');
				// $log->write('status: ' . $status, 'debug');

				/*
				 * Save the order in the database.
				 */
				$log->write("saveOrder:\n" . print_r([
					'transactionId' => $paymentId,
					'paymentUniqueId' => $token, 
					'paymentStatusId' => $status,
					'sendStatusMail' => true
				], true), 'debug');

				// savePaymentStatus
	            $this->saveOrder(
	                $paymentId,
	                $token,
	                $status,
	                true // sendStatusMail
	            );

                exit('OK');
			}
			elseif (!$payment->isOpen())
			{
				/*
				 * The payment isn't paid and isn't open anymore. We can assume it was aborted.
				 */
				$log->write("payment is NOT open anymore", 'trace');

				/**
				 * If an order has been created, set its status to CANCELLED
				 */
				$orderId = $this->getOrderNumber();
				if (!empty($orderId))
				{
					/*
					 * Update the order in the database.
					 */
					$log->write("saveOrder - set order status to PAYMENTSTATUS_CANCELLED:\n" . print_r([
						'transactionId' => $paymentId,
						'paymentUniqueId' => $token, 
						'paymentStatusId' => 'PAYMENTSTATUS_CANCELLED ' . static::PAYMENTSTATUS_CANCELLED,
						'sendStatusMail' => false
					], true), 'debug');

					// savePaymentStatus
		            $this->saveOrder(
		                $paymentId,
		                $token,
		                static::PAYMENTSTATUS_CANCELLED
		            );
				}

	            exit('OK');
			}
		}
		catch(Mollie_API_Exception $ex)
		{
			http_response_code(503);
			echo "Mollie exception: " . $ex->getMessage();
			$log->write('Mollie_API_Exception: ' . $ex->getMessage(), 'error');
		}
		catch(Exception $ex)
		{
			http_response_code(503);
			echo "Exception: " . $ex->getMessage();
			$log->write('Exception: ' . $ex->getMessage(), 'error');
		}

		exit;
	}

    /**
     * Return action method
     *
     * Called when customer returns to the shop
     */
	public function returnAction()
	{
		try
		{
			$log = new RequestLogger('return', $this);
			$log->writeGlobals('trace');

			$mollie = $this->getMollieClient();

			$paymentId = $this->getPaymentId();
			$token = $this->createPaymentToken();
			$log->write("paymentId: " . $paymentId . ' token: ' . $token, 'trace');

			// Get payment
			$payment = $mollie->payments->get($paymentId);
			$log->write("payment: \n" . print_r($payment, true), 'trace');

			// check for refunded status
			if (strtolower($payment->status) === 'refunded') {
				$log->write('Payment status === refunded', 'trace');

				$log->write("saveOrder:\n" . print_r([
					'transactionId' => $paymentId,
					'paymentUniqueId' => $token, 
					'paymentStatusId' => 'PAYMENTSTATUS_REFUNDED ' . static::PAYMENTSTATUS_REFUNDED,
					'sendStatusMail' => true
				], true), 'debug');

	            $this->saveOrder(
	                $paymentId,
	                $token,
	                static::PAYMENTSTATUS_REFUNDED,
	                true // sendStatusMail
	            );

	            return $this->redirect([ 'controller' => 'checkout' ]);
			}

			if ($payment->isPaid())
			{
				$success = true;

				// Validate basket signature (Shopware >= 5.3)
			    $signature = $payment->metadata->signature;
		
				if (method_exists($this, 'loadBasketFromSignature') && method_exists($this, 'verifyBasketSignature'))
				{
				    try
				    {
				        $basket = $this->loadBasketFromSignature($signature);
				        $this->verifyBasketSignature($signature, $basket);
				        $success = true;
				    } 
				    catch (Exception $ex) 
				    {
				        $success = false;
				        $log->write("Exception validating basket signature\nSignature: " . $signature, 'warn');
				    }
				}

			    if ($token != $payment->metadata->token)
			    {
			    	$log->write("createPaymentToken != payment->metadata->token\n" . $this->createPaymentToken() . ' != ' . $payment->metadata->token, 'warn');
			    	$success = false;
			    }


			    if( $success === false ) {
			    	// TODO: add message with error
			    	return $this->redirect([ 'controller' => 'checkout' ]);
			    }

				/*
				 * At this point you'd probably want to start the process of delivering the product to the customer.
				 */

				// check if the whole amount is paid
				// $status = $payment->getAmountRemaining() <= 0 ? static::PAYMENTSTATUS_PAID : static::PAYMENTSTATUS_PARTIAL_PAID;
				// $log->write('Payment amount remaining: ' . $payment->getAmountRemaining(), 'debug');
				// $log->write('status: ' . $status, 'debug');

				/*
				 * Save the order in the database.
				 */
				$log->write("saveOrder:\n" . print_r([
					'transactionId' => $paymentId,
					'paymentUniqueId' => $token, 
					'paymentStatusId' => 'PAYMENTSTATUS_PAID ' . static::PAYMENTSTATUS_PAID,
					'sendStatusMail' => true
				], true), 'debug');

				// savePaymentStatus
	            $this->saveOrder(
	                $paymentId,
	                $token,
	                static::PAYMENTSTATUS_PAID,
	                true // sendStatusMail
	            );

				$log->write('success && payment->isPaid', 'trace');
				return $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
			}
			elseif (!$payment->isOpen())
			{
				/*
				 * The payment isn't paid and isn't open anymore. We can assume it was aborted.
				 */
				$log->write("payment is NOT open anymore", 'trace');

				/**
				 * If an order has been created, set its status to CANCELLED
				 */
				$orderId = $this->getOrderNumber();

				if (!empty($orderId) )
				{
					/*
					 * Update the order in the database.
					 */
					$log->write("saveOrder - set order status to PAYMENTSTATUS_CANCELLED:\n" . print_r([
						'transactionId' => $paymentId,
						'paymentUniqueId' => $token, 
						'paymentStatusId' => 'PAYMENTSTATUS_CANCELLED ' . static::PAYMENTSTATUS_CANCELLED,
						'sendStatusMail' => false
					], true), 'debug');

					// savePaymentStatus
		            $this->saveOrder(
		                $paymentId,
		                $token,
		                static::PAYMENTSTATUS_CANCELLED
		            );
				}

				return $this->redirect([ 'controller' => 'checkout' ]);
			}
			else // payment is open
			{
				$log->write("saveOrder - set order status to PAYMENTSTATUS_OPEN:\n" . print_r([
					'transactionId' => $paymentId,
					'paymentUniqueId' => $token, 
					'paymentStatusId' => 'PAYMENTSTATUS_OPEN ' . static::PAYMENTSTATUS_OPEN,
					'sendStatusMail' => false
				], true), 'debug');

				// savePaymentStatus
	            $this->saveOrder(
	                $paymentId,
	                $token,
	                static::PAYMENTSTATUS_OPEN
	            );

	            $log->write('Payment open, customer redirected to finish', 'trace');
	            return $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
			}
		}
		catch(Exception $ex)
		{
			$log->write('Exception: ' . $ex->getMessage());

			return $this->redirect([ 'controller' => 'checkout' ]);
		}

		return $this->redirect([ 'controller' => 'checkout' ]);
	}

	/**
	 * Get the issuers for the iDEAL payment method
	 * Called in an ajax call on the frontend
	 */
	public function idealIssuersAction()
	{
		// TODO: check if all issuers are in the database
		
		try {
			$mollie = $this->getMollieClient();

			$issuers = $mollie->issuers->all();

			$idealIssuers = [];

			foreach ($issuers as $key => $issuer) {
				if ($issuer->method === Mollie_API_Object_Method::IDEAL) {

					if ($issuer->id === $this->getIdealIssuer())
					{
						$issuer->isSelected = true;
					}

					$idealIssuers[] = $issuer;
				}
			}

			$data = [
				'success' => true,
				'data' => $idealIssuers
			];

			header('Content-Type: application/json');
			echo json_encode($data);
			exit;
		}
		catch(Exception $ex)
		{
			$data = [
				'success' => false,
				'message' => $ex->getMessage()
			];

			http_response_code(500);
			header('Content-Type: application/json');
			echo json_encode($data);
			exit;
		}
	}

	/**
	 * Get an instance of the Mollie API client
	 * @return Mollie_API_Client
	 */
    protected function getMollieClient()
    {
		$apiKey = Shopware()->Config()->getByNamespace('Mollie', 'api-key');

		$mollie = new Mollie_API_Client;
		$mollie->setApiKey($apiKey);

		return $mollie;
    }

    /**
     * Create a token from the order data
     * @return string Token
     */
    protected function createPaymentToken()   
    {
    	$amount = $this->getAmount();

    	$user = $this->getUser();
    	$billing = $user['billingaddress'];
    	$customerId = $billing['customernumber'];

        return md5(implode('|', [ $amount, $customerId ]));
    }

    /**
     * Get order from the database with the ordernumber and the generated token
     * @param  string $orderNumber
     * @param  string $token
     * @return array               Order
     */
    protected function getOrder($orderNumber, $token)
    {
		$db = $this->container->get('db');

		$order = $db->executeQuery(
			'SELECT id, ordernumber, cleared, status, transactionID, paymentID, temporaryID FROM s_order ' . 
			'WHERE ordernumber = :ordernumber AND temporaryID = :temporaryID LIMIT 1',
			[ 
				'ordernumber' => $orderNumber,
				'temporaryID' => $token
			]
		)->fetch();

		return $order;
    }

    /**
     * Set the payment ID in the session
     */
    protected function setPaymentId($paymentId)
    {
    	Shopware()->Session()->sOrderVariables['paymentId'] = $paymentId;
    	return $paymentId;
    }

    /**
     * Get the payment ID from session
     */
    protected function getPaymentId()
    {
        if (!empty(Shopware()->Session()->sOrderVariables['paymentId'])) {
            return Shopware()->Session()->sOrderVariables['paymentId'];
        } else {
            return null;
        }
    }

    /**
     * Get the id of the chosen ideal issuer from session
     */
    protected function getIdealIssuer()
    {
        if (!empty(Shopware()->Session()->sUserVariables['mollie-ideal-issuer'])) {
            return Shopware()->Session()->sUserVariables['mollie-ideal-issuer'];
        } else {
            return null;
        }
    }
}
