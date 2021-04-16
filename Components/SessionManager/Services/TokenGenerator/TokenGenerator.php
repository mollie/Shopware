<?php

namespace MollieShopware\Components\SessionManager\Services\TokenGenerator;

use MollieShopware\Components\SessionManager\TokenGeneratorInterface;


class TokenGenerator implements TokenGeneratorInterface
{

    /**
     * @return string
     */
    public function generateToken()
    {
        $token = md5(rand(0, 100000) + strtotime(date('curr_date')));

        # token is longer than 20, so make it shorter
        $token = mb_strimwidth($token, 0, 25, '');

        $token = $token . "_" . time();

        # our final string should be no longer than 30 characters
        # otherwise we cannot really see it in the Mollie dashboard if the URL is too long
        $token = mb_strimwidth($token, 0, 30, '');

        return $token;
    }

}
