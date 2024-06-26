<?php

namespace MollieShopware\Components\Admin;

class AdminFactory implements AdminFactoryInterface
{
    /**
     * Returns the Shopware Admin module.
     *
     * @return \sAdmin
     */
    public function create()
    {
        return Shopware()->Modules()->Admin();
    }
}
