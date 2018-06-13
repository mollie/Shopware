<?php

	// Mollie Shopware Plugin Version: 1.1.0.4

use MollieShopware\Components\Base\AbstractPaymentController;
use MollieShopware\Components\RequestLogger;
use MollieShopware\Models\Transaction;
use MollieShopware\Components\Url;
use MollieShopware\Components\Helpers;
use MollieShopware\Components\Constants\PaymentStatus;

class Shopware_Controllers_Frontend_Mollie extends AbstractPaymentController
{
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
        if (!Helpers::stringContains($this->getPaymentShortName(), 'mollie_')) {
            throw new Exception('Wrong payment controller. Payment is not a Mollie payment.');
        }

        $this->redirect([ 'action' => 'direct', 'forceSecure' => true ]);
    }

    public function directAction()
    {

        $session = Shopware()->container()->get('session');




        $transaction = $this->getTransactionRepo()->createNew(
            $this->getUserId(),
            $this->getQuoteNumber(),
            $this->getPaymentId(),
            $this->getAmount(),
            $this->getCurrencyShortName(),
            $this->generateToken($this->getQuoteNumber()),
            $this->generateSignature()
        );

        $mollie = $this->container->get('mollie_shopware.api');


        $webhookUrl = $this->Front()->Router()->assemble([
            'controller' => 'Mollie',
            'action' => 'notify',
            'appendSession' => false,
            'forceSecure' => true
        ]) . '?' . $transaction->getQuerystring();

        $returnUrl  = $this->Front()->Router()->assemble([
            'controller' => 'Mollie',
            'action' => 'return',
            'appendSession' => false,
            'forceSecure' => true,
            'quote_number' => $this->getQuoteNumber(),
        ]) . '?' . $transaction->getQuerystring();

        if (defined('LOCAL_MOLLIE_DEV') && LOCAL_MOLLIE_DEV){
            $webhookUrl = 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($webhookUrl);
            $returnUrl = 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($returnUrl);
        }

        if (method_exists($this, 'getCurrencyISO')){
            $currency = $this->getCurrencyISO('EUR');
        }
        else{
            $currency =  $this->getCurrencyShortName();
        }

        if (!$currency){
            $currency = 'EUR';
        }

        $paymentOptions = [
            'amount'       => [
                'value'=>number_format($this->getAmount(), 2, '.', ''),
                'currency'=>
                    $currency
            ],
            'description'  => $this->getPaymentShortName(), // TODO: give decent description
            'redirectUrl'  => $returnUrl,
            'webhookUrl'   => $webhookUrl,
            'method'       => str_replace('mollie_', '', $this->getPaymentShortName()),
            'metadata'     => [
                'quoteNumber' => $this->getQuoteNumber(),
                'token' => $this->generateToken(),
                'signature' => $this->generateSignature(),
                'session'=>$session['sessionId'],
            ],
        ];




        if (Helpers::stringContains($this->getPaymentShortName(), 'ideal')) {
            $paymentOptions['issuer'] = $this->getIdealIssuer();
        }

        try{
            $molliePayment = $mollie->payments->create($paymentOptions);
        }
        catch(\Mollie\Api\Exceptions\ApiException $e){

            Shopware()->Session()->mollieError = $e->getMessage();

            // Collecting a list of available messages:
            //
            // Error executing API call (request): The issuer is invalid.
            // Error executing API call (request): The redirect location is invalid.
            // Error executing API call (request): The webhook location is invalid.
            //

            return $this->redirectBack();

        }
        catch(Exception $e){

            Shopware()->Session()->mollieError = get_class($e) . ' : ' . $e->getMessage();
            return $this->redirectBack();

        }
//        finally{
//
//            Shopware()->Session()->mollieError = 'Er heeft zich een onbekende fout voorgedaan.';
//            return $this->redirectBack();
//
//        }


        $transaction->setTransactionId($molliePayment->id);

        $this->getTransactionRepo()
            ->save($transaction);


        $checkoutUrl = $molliePayment->getCheckoutUrl();


        echo $checkoutUrl . '<br />';
        echo $webhookUrl . '<br />';
        die();


        // redirect customer to Mollie
        $this->redirect($checkoutUrl);

    }

    /**
     * Webhook action method
     *
     * Called by Mollie when the payment has a new status
     *
     * Notify is an url that's called by Mollie, so you don't need a view.
     * But don't call $this->Front()->Plugins()->ViewRenderer()->setNoRender();
     * This wrecks the current session!!!
     */
    public function notifyAction()
    {
        $logger = new RequestLogger('notify');
        $transaction = null;

        try {
            $config = $this->container->get('mollie_shopware.config');
            $mollie = $this->container->get('mollie_shopware.api');

            /*
             * Retrieve the payment's current state.
             */
            $paymentId = $this->Request()->getParam('id', null);

            $mollie_transaction_id = null;
            if ($transaction = $this->getTransactionRepo()->getByID($paymentId)){
                $mollie_transaction_id = $transaction->getTransactionId();
            }



            if (empty($paymentId) || empty($mollie_transaction_id)) {
                $this->Front()->Plugins()->ViewRenderer()->setNoRender();
                return $this->sendResponse([ 'message' => 'No paymentid given', 'success' => false ], 400);
            }

            $logger->write('PaymentId: ' . $mollie_transaction_id);

            $molliePayment = $mollie->payments->get($mollie_transaction_id);
            $token = $molliePayment->metadata->token;
            $quoteNumber = $molliePayment->metadata->quoteNumber;
            $logger->write('token: ' . $token);
            $logger->write('quoteNumber: ' . $quoteNumber);

            $transaction = $this->getTransactionRepo()
                ->getByQuoteNumber($quoteNumber);
            $logger->write('transaction id: ' . $transaction->getId());

            if (strtolower($molliePayment->status) === 'refunded') {
                $status = PaymentStatus::REFUNDED;
            } else if ($molliePayment->isPaid()) {
                $status = PaymentStatus::PAID;
            } else if ($molliePayment->isOpen()) {
                $status = PaymentStatus::OPEN;
            } else {
                $status = PaymentStatus::CANCELLED;
            }

            $logger->write('status: ' . $status);
            $this->getTransactionRepo()->updateStatus($transaction, $status);

            if (!empty($transaction->getOrderNumber())) {
                $logger->write('Has order.');
                $this->savePaymentStatus($paymentId, $token, $status, $config->sendStatusMail());

            } else if (!$this->hasSession()) {
                $this->Front()->Plugins()->ViewRenderer()->setNoRender();
                $logger->write('Has no order yet. But no session to create it.');
                return $this->sendResponse([ 'message' => "Couldn't create order. No session available", 'success' => false ], 500);

            } else {
                $logger->write('Has no order yet.');

                if (!in_array($status, [ PaymentStatus::OPEN, PaymentStatus::PAID ])) {
                    $this->Front()->Plugins()->ViewRenderer()->setNoRender();
                    $logger->write("With the current paymentstatus an order won't be created!");
                    return $this->sendResponse([ 'message' => "No action needed. Order not created.", 'success' => true ], 200);
                }

                $signature = $molliePayment->metadata->signature;

                if (!$this->checkSignature($signature)) {
                    $logger->write("Signature invalid");
                    return $this->sendResponse([ 'message' => "Signature invalid", 'success' => true ], 200);
                }

                $orderNumber = $this->saveOrder($paymentId, $token, $status, false);

                $logger->write('orderNumber: ' . $orderNumber);
                $this->getTransactionRepo()->updateOrderNumber($transaction, $orderNumber);
            }

            $logger->write('Success');
            return $this->sendResponse([ 'message' => 'Succesfully updated status', 'success' => true ]);
        } catch (Exception $ex) {
            $logger->write('Exception message: ' . $ex->getMessage());
            $logger->write('Exception trace: ' . $ex->getTraceAsString());

            if (!empty($transaction)) {
                $this->getTransactionRepo()->addException($transaction, $ex);
            }

            $logger->write("Failed");
            return $this->sendResponse([ 'message' => "Exception: {$ex->getMessage()}", 'success' => false], 500);
        }

        $logger->write("Fall through. This shouldn't happen");
        return $this->sendResponse([ 'message' => "Something went wrong!", 'success' => false ], 500);
    }

    /**
     * Return action method
     *
     * Called when customer returns to the shop
     */
    public function returnAction()
    {

        $session = Shopware()->Container()
            ->get('session');



        // Prepare dependant objects
        $config = $this->container->get('mollie_shopware.config');
        $mollie = $this->container->get('mollie_shopware.api');

        /*
         * find transaction in our database,
         * based on id= in query string
         */
        $transaction = $this->getTransactionRepo()
            ->getById($this->Request()->get('id'));

        // Throw an error if the transaction cannot be found by ID
        if (!$transaction) {
            return $this->paymentError('Er ging iets mis bij het controleren van de betaalstatus. Probeer het opnieuw. (#notransaction)');
        }

        // Check the transaction's checksum to make sure the
        // transaction hasn't been hijacked.
        if ($transaction->getChecksum() != $this->Request()->getParam('cs')){
            return $this->paymentError('Er ging iets mis bij het controleren van de betaalstatus. Probeer het opnieuw. (#sessionhijacking)');
        }
        
        /*
         * Check if transaction had been paid before. We do not
         * accept any changes to the payment status as soon
         * as the payment has been approved
         */
        if ($transaction->getStatus() === 'paid') {
            return $this->redirectToFinish();
        }

        // Get Mollie Payment object (which contains the payment status)
        $molliePayment = $mollie->payments
            ->get($transaction->getTransactionId());


        foreach([
            'refunded'      =>  PaymentStatus::REFUNDED,
            'paid'          =>  PaymentStatus::PAID,
            'open'          =>  PaymentStatus::OPEN,
            'default'       =>  PaymentStatus::CANCELLED,
        ] as $status_label => $status_code){

            // Check status to set status code
            if (strtolower($molliePayment->status) === $status_label || $status_label === 'default'){
                $status = $status_code;
                break;
            }

        }
        // Update transaction status
        $this->getTransactionRepo()
            ->updateStatus($transaction, $status);

        /*
         * If the user is no longer logged in or a different user
         * than the one starting the transaction is logged in
         * we need to log in the original user again.
         * */
        if ($session->sessionId !== $molliePayment->metadata->session){

            // user is no longer logged in
            $db = shopware()->container()->get('db');

            // @todo: flexibilize the users table
            $user = $db->fetchRow(sprintf('
              SELECT * 
              FROM s_user 
              WHERE id=%d
              ', $transaction->getUserId()));

            if (!$user){
                throw new Exception('Cannot find user with ID ' . $user['id']);
            }

            // restore user session
            $this->session('sUserMail', $user['email']);
            $this->session('sUserPassword', $user['password']);
            $this->session('sUserId', $user['id']);

            $q = $db->prepare('
              UPDATE 
                s_order_basket 
              SET sessionID=? 
              WHERE sessionID=?
            ');

            $q->execute([
                $session->sessionId,
                $molliePayment->metadata->session,
            ]);

        }



        if ($status === PaymentStatus::PAID) {

            $orderNumber = $this->saveOrder($molliePayment->id, $molliePayment->metadata->token, $status, false);
            $this->getTransactionRepo()->updateOrderNumber($transaction, $orderNumber);

            // Update order status
            $this->savePaymentStatus(
                $transaction->getTransactionId(),
                $transaction->getSessionID(),
                $status,
                $config->sendStatusMail()
            );

            return $this->redirectToFinish();

        }

        if ($status === PaymentStatus::OPEN) {
            // @todo: is this a status we can expect?
            $this->paymentError('We hebben de betaling nog niet ontvangen');
        }
        else{
            // Payment failed. Show graceful error.
            $this->paymentError('De betaling is geannuleerd of mislukt. Probeer het opnieuw.');
        }

        return $this->redirectBack();
    }


    /**
     * Get the issuers for the iDEAL payment method
     * Called in an ajax call on the frontend
     */
    public function idealIssuersAction()
    {

        $this->setNoRender();
        
        try {
            $ideal = $this->container->get('mollie_shopware.payment_methods.ideal');

            $idealIssuers = $ideal->getIssuers();

            return $this->sendResponse([ 'data' => $idealIssuers, 'success' => true ]);
        } catch (Exception $ex) {
            return $this->sendResponse([ 'message' => $ex->getMessage(), 'success' => false ], 500);
        }
    }

    /**
     * Get the id of the chosen ideal issuer from database
     */
    protected function getIdealIssuer()
    {
        $ideal = $this->container->get('mollie_shopware.payment_methods.ideal');
        return $ideal->getSelectedIssuer();
    }

    /**
     * Sets a session variable
     *
     * @param string $variable The variable to be set in Session storage
     * @param mixed $value The variable's value
     */
    protected function session($variable, $value)
    {

        Shopware()->Session()
            ->$variable = $value;

    }

    /**
     * Sends the user back to the payment screen with the given error
     *
     * @param $error
     */
    protected function paymentError($error)
    {
        $this->session('mollieStatusError', $error);
        $this->redirectBack();
    }


    /**
     * Return the ISO code for the currency that's being used
     */
    public function getCurrencyISO($default = 'EUR')
    {
        $basket = $this->getBasket();

        return $basket ? $basket['sCurrencyName'] : $default;
    }
}
