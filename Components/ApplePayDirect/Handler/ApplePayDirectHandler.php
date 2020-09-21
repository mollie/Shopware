<?php

namespace MollieShopware\Components\ApplePayDirect\Handler;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart;
use MollieShopware\Components\Shipping\Shipping;

/**
 * @copyright 2020 dasistweb GmbH (https://www.dasistweb.de)
 */
class ApplePayDirectHandler implements ApplePayDirectHandlerInterface
{

    /**
     * This is the key for the session entry
     * that stores the payment token before
     * finishing a new order
     */
    const KEY_SESSION_PAYMENTTOKEN = 'MOLLIE_APPLEPAY_PAYMENTTOKEN';


    /**
     * @var MollieApiClient
     */
    private $clientLive;

    /**
     * @var \sAdmin
     */
    private $admin;

    /**
     * @var \sBasket
     */
    private $basket;

    /**
     * @var Shipping $shipping
     */
    private $shipping;

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;


    /**
     * @param MollieApiClient $clientLive
     * @param $sAdmin
     * @param $sBasket
     * @param Shipping $cmpShipping
     * @param \Enlight_Components_Session_Namespace $session
     */
    public function __construct(MollieApiClient $clientLive, $sAdmin, $sBasket, Shipping $cmpShipping, \Enlight_Components_Session_Namespace $session)
    {
        $this->clientLive = $clientLive;

        $this->admin = $sAdmin;
        $this->basket = $sBasket;
        $this->shipping = $cmpShipping;
        $this->session = $session;
    }


    /**
     * @return mixed|ApplePayCart
     * @throws \Enlight_Exception
     */
    public function buildApplePayCart()
    {
        $cart = new ApplePayCart();

        $taxes = 0;

        /** @var array $item */
        foreach ($this->basket->sGetBasketData()['content'] as $item) {

            $cart->addItem(
                $item['ordernumber'],
                $item['articlename'],
                (int)$item['quantity'],
                (float)$item['priceNumeric']
            );

            $taxes += (float)str_replace(',', '.', $item['tax']);
        }

        # load our purchase country
        # while we still show the apple pay sheet
        # this is always handled through this variable.
        $country = $this->admin->sGetUserData()['additional']['country'];

        /** @var array $shipping */
        $shipping = $this->admin->sGetPremiumShippingcosts($country);
        
        if ($shipping['brutto'] !== null && $shipping['brutto'] > 0) {

            /** @var array $shipmentMethod */
            $shipmentMethod = $this->shipping->getCartShippingMethod();

            $cart->setShipping($shipmentMethod['name'], (float)$shipping['brutto']);

            $taxes += ($shipping['brutto'] - $shipping['netto']);
        }

        # also add our taxes value
        # if we have one
        if ($taxes > 0) {
            $cart->setTaxes($taxes);
        }

        return $cart;
    }

    /**
     * @param $domain
     * @param $validationUrl
     * @return mixed|string
     */
    public function requestPaymentSession($domain, $validationUrl)
    {
        # attention!!!
        # for the payment session request with apple 
        # we must ALWAYS use the live api key
        # the test will never work!!!
        $responseString = $this->clientLive->wallets->requestApplePayPaymentSession(
            $domain,
            $validationUrl
        );

        return (string)$responseString;
    }

    /**
     * @param string $token
     */
    public function setPaymentToken($token)
    {
        $this->session->offsetSet(self::KEY_SESSION_PAYMENTTOKEN, $token);
    }

    /**
     * @return string
     */
    public function getPaymentToken()
    {
        return $this->session->offsetGet(self::KEY_SESSION_PAYMENTTOKEN);
    }

}