<?php

namespace MollieShopware\Components\ApplePayDirect;

use MollieShopware\Components\ApplePayDirect\Handler\ApplePayDirectHandler;
use MollieShopware\Components\ApplePayDirect\Models\UserData\UserData;

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
     * @param UserData $userData
     */
    public function setUserData(UserData $userData);

    /**
     * @return null|UserData
     */
    public function getUserData();

    /**
     * @return mixed
     */
    public function clearUserData();

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
