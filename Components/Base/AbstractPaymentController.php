<?php

namespace MollieShopware\Components\Base;

use Shopware\Components\CSRFWhitelistAware;
use Shopware_Controllers_Frontend_Payment;

abstract class AbstractPaymentController extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * Because Shopware has no order number
     * when the payment is created,
     * This number can be found in both Shopware as in Mollie
     *
     * @var string
     */
    protected $quoteNumber;

    /**
     * Basket signature for validation
     * Save the signature here to prevent to persist the basket twice
     *
     * @var string
     */
    protected $signature = '';

    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return [];
    }

    /**
     * Plugin is valid for 5.2.13 and higher
     * The signature feature is available from 5.3 and higher
     * This function checks if the methods necessary are available
     *
     * @return boolean
     */
    protected function shopwareHasSignatureFeature()
    {
        return method_exists($this, 'loadBasketFromSignature')
            && method_exists($this, 'verifyBasketSignature')
            && method_exists($this, 'persistBasket');
    }

    /**
     * Check if there is an order basket in the session
     *
     * @return boolean
     */
    protected function hasOrderBasketInSession()
    {
        return !empty($this->container->get('session')->offsetGet('sOrderVariables'))
            && isset($this->container->get('session')->offsetGet('sOrderVariables')['sBasket'])
            && isset($this->container->get('session')->offsetGet('sOrderVariables')['sBasket']['content']);
    }

    /**
     * Generate a basket signature
     * It is used to validate the contents of the basket
     *
     * https://developers.shopware.com/developers-guide/payment-plugin/#generate-signature
     *
     * @return string signature
     */
    protected function generateSignature()
    {
        if (empty($this->signature) && $this->shopwareHasSignatureFeature() && $this->hasOrderBasketInSession()) {
            $this->signature = $this->persistBasket();
        }

        return $this->signature;
    }

    /**
     * Check the basket signature is correct
     *
     * When Shopware has no signature feature (Shopware < 5.3),
     * always return true
     *
     * @param string $signature
     * @param integer $amount
     * @return boolean
     */
    protected function checkSignature($signature)
    {
        if ($this->shopwareHasSignatureFeature()) {
            try {
                $basket = $this->loadBasketFromSignature($signature);
                $this->verifyBasketSignature($signature, $basket);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send a json response
     *
     * @param array $data
     * @param integer $httpCode
     */
    protected function sendResponse(array $data = [], $httpCode = 200)
    {
        $this->Response()->setHttpResponseCode($httpCode);
        $this->Response()->setHeader('Content-type', 'application/json', true);
        $this->Response()->setBody(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * If it has an ordernumber, the order has already been saved
     * and the cart has been emptied
     *
     * @return boolean
     */
    protected function hasOrder()
    {
        return !empty($this->getOrderNumber());
    }

    /**
     * Get the id of the currently logged in user
     *
     * @return int
     */
    protected function getUserId()
    {
        $session = $this->container->get('session');
        return empty($session->sUserId) ? $session['auto-user'] : $session->sUserId;
    }

    /**
     * Get the id of the selected payment method
     *
     * @return int
     */
    protected function getPaymentId()
    {
        $paymentId = null;
        $user = $this->getUser();
        $userId = $this->getUserId();

        if (!empty($user['additional']['payment']['id'])) {
            $paymentId = $user['additional']['payment']['id'];
        }

        if (!empty($user['additional']['user']['paymentID'])) {
            $paymentId = $user['additional']['user']['paymentID'];
        }

        if ($paymentId === null && $userId !== null) {
            $connection = $this->container->get('models')->getConnection();
            $paymentId = $connection->fetchColumn('SELECT paymentID FROM s_user WHERE id = :userId', ['userId' => $userId]);
        }

        $user['additional']['payment']['id'] = $paymentId;
        $user['additional']['user']['paymentID'] = $paymentId;

        return $paymentId;
    }

    /**
     * Get the Order Repository
     *
     * @return \Shopware\Models\Order\Repository
     */
    public function getOrderRepository()
    {
        return $this->container->get('models')->getRepository(
            \Shopware\Models\Order\Order::class
        );
    }

    /**
     * Get the Transaction Repository
     *
     * @return \MollieShopware\Models\TransactionRepository
     */
    public function getTransactionRepository()
    {
        return $this->container->get('models')->getRepository(
            \MollieShopware\Models\Transaction::class
        );
    }

    /**
     * Check if order details are in the session
     *
     * @return boolean
     */
    protected function hasSession()
    {
        return !empty($this->container->get('session')->sOrderVariables['sUserData']['additional']['user']['customernumber']);
    }

    /**
     * Persist the order model.
     *
     * @param \Shopware\Models\Order\Order $order
     *
     * @throws \Exception
     */
    protected function persistOrder($order)
    {
        Shopware()->Models()->persist($order);
        Shopware()->Models()->flush();
    }

    /**
     * Wrapper function for persistbasket, which is declared protected
     * and cannot be called from outside.
     *
     * @return string
     */
    protected function doPersistBasket()
    {
        if (method_exists($this, 'persistBasket')) {
            return parent::persistBasket();
        } else {
            return $this->persistBasketLegacy();
        }
    }

    /**
     * This function is used to return a basket signature in versions
     * before Shopware 5.3.
     * It just needs a random string. So it did indeed work in older versions.
     * @return string
     */
    private function persistBasketLegacy()
    {
        return $this->generateRandomString(10);
    }

    /**
     * @param $length
     * @return string
     */
    private function generateRandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

}
