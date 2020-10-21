<?php

use MollieShopware\Components\ApplePayDirect\Models\Button\DisplayOption;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayDirectDisplayOptions;

class Shopware_Controllers_Backend_MollieApplePayDirect extends Shopware_Controllers_Backend_Application
{

    /**
     * We have to set a model for every backend controller.
     * So let's just use this one.
     */
    protected $model = \MollieShopware\Models\Transaction::class;
    protected $alias = 'mollie_order';

    /**
     * Gets the available display restrictions of Apple Pay Direct.
     * This is used for the multi select dropdown in the plugin configuration.
     */
    public function displayRestrictionsAction()
    {
        /** @var ApplePayDirectDisplayOptions $restrictionServices */
        $restrictionServices = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.services.display_option');

        $restrictions = $restrictionServices->getDisplayOptions();

        $data = [];

        /** @var DisplayOption $options */
        foreach ($restrictions as $options) {

            $data[] = array(
                'id' => $options->getId(),
                'name' => $options->getName(),
            );
        }

        $this->view->assign([
            'data' => $data,
            'total' => count($data),
        ]);
    }

}
