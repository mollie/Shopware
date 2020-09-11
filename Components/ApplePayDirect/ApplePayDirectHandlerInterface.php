<?php

namespace MollieShopware\Components\ApplePayDirect;

/**
 * @copyright 2020 dasistweb GmbH (https://www.dasistweb.de)
 */
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
