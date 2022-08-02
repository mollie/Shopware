<?php

namespace MollieShopware\Components\SessionManager\Services\Cookies;

use MollieShopware\Components\SessionManager\CookieRepositoryInterface;

class CookieRepository implements CookieRepositoryInterface
{

    /**
     * @param $previousSessionId
     * @return void
     */
    public function setSessionCookie($previousSessionId)
    {
        # we have to use the plain cookie command
        # because different shopware versions use different approaches to
        # set it in the controller response
        setcookie(
            session_name(),
            $previousSessionId,
            0,
            ini_get('session.cookie_path'),
            null,
            $this->isRequestSecure()
        );
    }

    /**
     * @return bool
     */
    private function isRequestSecure()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    }
}
