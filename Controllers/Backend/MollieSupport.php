<?php

use MollieShopware\Traits\Controllers\BackendControllerTrait;
//use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Payment\Payment;
//Shopware_Controllers_Backend_ExtJs
class Shopware_Controllers_Backend_MollieSupport extends Shopware_Controllers_Backend_Application
{
    use BackendControllerTrait;

    protected $model = Payment::class;
}
