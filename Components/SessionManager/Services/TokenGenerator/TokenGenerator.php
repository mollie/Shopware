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

        # token is longer than 20, only use 10 characters from it
        $token = mb_strimwidth($token, 0, 10, '');

        # now append a random string
        $token = $token . $this->generateRandomString(20);

        # our final string should be no longer than 25 characters
        # otherwise we cannot really see it in the Mollie dashboard if the URL is too long
        $token = mb_strimwidth($token, 0, 25, '');

        return $token;
    }

    /**
     * @param int $length
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
