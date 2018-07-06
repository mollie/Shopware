<?php

	// Mollie Shopware Plugin Version: 1.2

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

        $this->redirect([

            'action'        => 'direct',
            'forceSecure'   => true,

        ]);

    }

    public function directAction()
    {

        $order_service = Shopware()->Container()
            ->get('mollie_shopware.order_service');


        $signature = $this->doPersistBasket();


        $payment_service = Shopware()->Container()
            ->get('mollie_shopware.payment_service');


        $currency = method_exists($this, 'getCurrencyISO') ? $this->getCurrencyISO('EUR') : 'EUR';

        $payment_id = $payment_service->createPaymentEntry($this, $signature)
            ->getID();


        $webhookUrl = $this->Front()->Router()->assemble([

            'controller' => 'Mollie',
            'action' => 'notify',
            'forceSecure' => true,

            'payment_id'    => $payment_id,
            'signature'     => $signature,
            'checksum'      => $order_service->checksum($signature, $payment_id, get_called_class()),

        ]);

        $returnUrl  = $this->Front()->Router()->assemble([

            'controller'    => 'Mollie',
            'action'        => 'return',
            'forceSecure'   => true,

            'payment_id'    => $payment_id,
            'signature'     => $signature,
            'checksum'      => $order_service->checksum($signature, $payment_id, get_called_class()),

        ]);


        if (defined('LOCAL_MOLLIE_DEV') && LOCAL_MOLLIE_DEV){
            $returnUrl = 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($returnUrl);
            $webhookUrl = 'https://kiener.nl/kiener.mollie.feedback.php?to=' . base64_encode($webhookUrl);
        }



        // create new Mollie transaction and store transaction ID in database
        try{
            $transaction = $payment_service->startTransaction($signature, $returnUrl, $webhookUrl, $payment_id, $this->getAmount(), $currency, $this->getPaymentShortName());
        }
        catch (\Exception $e){
            return $this->redirectBack($e->getMessage());
        }

        $checkoutUrl = $transaction->getCheckoutUrl();

        $this->redirect($checkoutUrl);


    }

    public function returnAction()
    {


        $order_service = Shopware()->Container()
            ->get('mollie_shopware.order_service');

        $payment_service = Shopware()->Container()
            ->get('mollie_shopware.payment_service');


        $signature = $this->request()->getParam('signature');
        $checksum = $this->request()->getParam('checksum');
        $payment_id = $this->request()->getParam('payment_id');




        if ($order_service->checksum($signature, $payment_id, get_called_class()) === $checksum){



            if (!$payment_service->hasSession()){
                /*die('restore session');
                $payment_service->restoreSession($signature);

                try {

                    $this->loadBasketFromSignature($signature);

                } catch (Exception $e) {
                    // cannot restore basket
                    return $this->redirectBack();
                }*/

            }



            if ($transaction = $payment_service->getPaymentStatus($this, $signature, $payment_id)) {


                $orderNumber = $this->saveOrder($payment_id, $signature, PaymentStatus::PAID, true);

                $this->getTransactionRepo()->updateOrderNumber($transaction, $orderNumber);
                // payment succeeded. Send to confirmation screen
                return $this->redirectToFinish();

            } else {
                // payment failed. Give user another chance
                return $this->redirectBack('Payment failed');

            }
        }
        return $this->redirectBack('No session');

    }

    public function notifyAction()
    {

return;
        $order_service = Shopware()->Container()
            ->get('mollie_shopware.order_service');

        $payment_service = Shopware()->Container()
            ->get('mollie_shopware.payment_service');


        $signature = $this->request()->getParam('signature');
        $checksum = $this->request()->getParam('checksum');
        $payment_id = $this->request()->getParam('payment_id');



        if ($order_service->checksum($signature, $payment_id, get_called_class()) === $checksum){

            $payment_service->restoreSession($signature);

            try {
                $this->loadBasketFromSignature($signature);
            }
            catch(Exception $e){

                // cannot restore basket
                $this->notifyException('Cannot restore basket');
            }


            if ($payment_service->getPaymentStatus($this, $signature, $payment_id)){

                // payment succeeded. Send to confirmation screen
                $this->notifyOK('Thank you');

            }
            else{

                // payment failed. Give user another chance
                $this->notifyOK('Error registered properly');

            }

        }

        $this->notifyException('Checksum error');

    }

    public function oldDirectAction()
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
            'redirectUrl'  => $redirectUrl,
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
    public function oldNotifyAction()
    {

        $config = $this->container->get('mollie_shopware.config');
        $mollie = $this->container->get('mollie_shopware.api');

        $logger = new RequestLogger('notify');
        $transaction = null;

        // Collect local payment information
        $localPaymentID = $this->Request()->getParam('id', null);
        if ($localPaymentID){

            if ($transaction = $this->getTransactionRepo()->getByID($localPaymentID)){

                if ($transaction->getChecksum() === $this->Request()->getParam('cs', null)){
                    $remotePaymentID = $transaction->getTransactionId();
                }
                else{
                    return $this->notifyException('Local checksum error');
                }

            }
            else{
                return $this->notifyException('No local payment found');
            }

        }
        else{
            return $this->notifyException('No local payment ID given');
        }


        // Collect remote payment information
        try{
            $molliePayment = $mollie->payments->get($remotePaymentID);
        }
        catch(Exception $e){
            return $this->notifyException('An unexpected error occurred (' . get_class($e) . ')');
        }

        $token = $molliePayment->metadata->token;
        $quoteNumber = $molliePayment->metadata->quoteNumber;

        $transaction = $this->getTransactionRepo()
            ->getByQuoteNumber($quoteNumber);

        if (strtolower($molliePayment->status) === 'refunded') {
            $status = PaymentStatus::REFUNDED;
        } else if ($molliePayment->isPaid()) {
            $status = PaymentStatus::PAID;
        } else if ($molliePayment->isOpen()) {
            $status = PaymentStatus::OPEN;
        } else {
            $status = PaymentStatus::CANCELLED;
        }

        $this->getTransactionRepo()->updateStatus($transaction, $status);


        // @todo: refactor from here

        /*
         * Refactoring guidelines:
         *
         * 1. At this point no one should have a session as this is a callback
         *    mechanism from Mollie (which is called separate from the active
         *    user's session.
         *
         * 2. There should always be an order at this point. If there isn't
         *    we have an unconnected payment which should never happen
         *
         * 3. The mechanism we use for finding the order should also be used
         *    in the direct callback from Mollie. We should never allow any
         *    discrepancy between the two actions (notify and return).
         *
         * 4. Create a service provider for this piece of code.
         *
         * */

        $logger->write('status: ' . $status);

        if (!empty($transaction->getOrderNumber())) {
            $logger->write('Has order.');
            $this->savePaymentStatus($localPaymentID, $token, $status, $config->sendStatusMail());

        }
        else if (!$this->hasSession()) {

            $logger->write('Has no order yet. But no session to create it.');
            return $this->notifyException('Cannot create order. No session available');

        }
        else {

            $logger->write('Has no order yet.');

            if (!in_array($status, [ PaymentStatus::OPEN, PaymentStatus::PAID ])) {
                $logger->write('With the current paymentstatus an order won\'t be created!');
                return $this->notifyException('No action needed. Order not created');
            }

            $signature = $molliePayment->metadata->signature;

            if (!$this->checkSignature($signature)) {
                $logger->write('Signature invalid');
                return $this->notifyException('Invalid signature');
            }

            $orderNumber = $this->saveOrder($localPaymentID, $token, $status, false);

            $logger->write('orderNumber: ' . $orderNumber);
            $this->getTransactionRepo()->updateOrderNumber($transaction, $orderNumber);

        }

        // @todo: refactor to here

        $logger->write('Success');
        return $this->notifyOK('Succesfully updated status');


    }

    private function notifyException($error){

        header('HTTP/1.0 500 Server Error');
        header('Content-Type: text/json');
        echo json_encode(['success'=>false, 'message'=>$error], JSON_PRETTY_PRINT);
        die();

    }

    private function notifyOK($msg){

        header('HTTP/1.0 200 Ok');
        header('Content-Type: text/json');
        echo json_encode(['success'=>true, 'message'=>$msg], JSON_PRETTY_PRINT);
        die();

    }

    /**
     * Return action method
     *
     * Called when customer returns to the shop
     */
    public function oldReturnAction()
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

    protected function loadBasketFromSignature($signature)
    {
        return parent::loadBasketFromSignature($signature);
    }

    /*
     * Wrapper function for persistbasket, which is declared protected
     * and cannot be called from outside
     *
     * @todo: there must be a more elegant way to do this!
     * */
    public function doPersistBasket()
    {
        /** @var Enlight_Components_Session_Namespace $session */
        $session = $this->get('session');
        $basket = $session->offsetGet('sOrderVariables')->getArrayCopy();
        $customerId = $session->offsetGet('sUserId');


        $signature_service = Shopware()->Container()
            ->get('mollie_shopware.signature_service');

        $signature = $signature_service->generateSignature(
            $basket['sBasket'],
            $customerId
        );

        $basket_persist_service =  Shopware()->Container()
            ->get('mollie_shopware.basket_persist_service');

        $basket_persist_service->persist($signature, $basket);

        return $signature;
    }
}
