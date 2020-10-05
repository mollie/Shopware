<?php

namespace MollieShopware\Components\ApplePayDirect;


interface ApplePayDirectHandlerInterface
{

    /**
     * @return mixed
     */
    public function buildApplePayCart();

    /**
     * @param $domain
     * @param $validationUrl
     * @return mixed
     */
    public function requestPaymentSession($domain, $validationUrl);

    /**
     * @param $token
     * @return mixed
     */
    public function setPaymentToken($token);

    /**
     * @return mixed
     */
    public function getPaymentToken();

}
