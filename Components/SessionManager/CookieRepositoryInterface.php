<?php

namespace MollieShopware\Components\SessionManager;

interface CookieRepositoryInterface
{

    /**
     * @param $previousSessionId
     * @return mixed
     */
    public function setSessionCookie($previousSessionId);
}
