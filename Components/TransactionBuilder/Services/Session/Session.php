<?php

namespace MollieShopware\Components\TransactionBuilder\Services\Session;


class Session implements SessionInterface
{

    /**
     * @return string
     */
    public function getSessionId()
    {
        return Shopware()->Container()->get('sessionid');

        # todo verify old versions
        #return \Enlight_Components_Session::getId();
    }

}
