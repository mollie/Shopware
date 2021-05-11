<?php

namespace MollieShopware\Components\TransactionBuilder\Services\Session;


class Session implements SessionInterface
{

    /**
     * @return string
     */
    public function getSessionId()
    {
        return \Enlight_Components_Session::getId();
    }

}
