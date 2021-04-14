<?php

namespace MollieShopware\Traits\Controllers;


trait RedirectTrait
{

    /**
     * @var string
     */
    private $ERROR_PAYMENT_FAILED = 'Payment failed';


    /**
     * @param \Enlight_Controller_Action $controller
     * @param $transactionId
     * @throws \Exception
     */
    protected function redirectToMollieFinishPayment(\Enlight_Controller_Action $controller, $transactionId)
    {
        $url = Shopware()->Front()->Router()->assemble([
            'controller' => 'Mollie',
            'action' => 'finish',
            'transactionNumber' => $transactionId
        ]);

        $controller->redirect($url);
    }

    /**
     * @param \Enlight_Controller_Action $controller
     * @throws \Exception
     */
    protected function redirectToShopwareCheckoutFailed(\Enlight_Controller_Action $controller)
    {
        Shopware()->Session()->offsetSet('mollieError', $this->ERROR_PAYMENT_FAILED);

        $url = Shopware()->Front()->Router()->assemble([
            'controller' => 'checkout',
            'action' => 'confirm'
        ]);

        $controller->redirect($url);
    }

    /**
     * @param \Enlight_Controller_Action $controller
     * @param $uniqueId
     * @throws \Exception
     */
    protected function redirectToShopwareCheckoutFinish(\Enlight_Controller_Action $controller, $uniqueId)
    {
        $url = Shopware()->Front()->Router()->assemble([
            'controller' => 'checkout',
            'action' => 'finish',
            'sUniqueID' => $uniqueId
        ]);

        $controller->redirect($url);
    }

}
