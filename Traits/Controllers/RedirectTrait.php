<?php

namespace MollieShopware\Traits\Controllers;

trait RedirectTrait
{

    /**
     * @var string
     */
    private $ERROR_PAYMENT_FAILED = 'Payment failed';

    /**
     *
     */
    private $ERROR_PAYMENT_FAILED_RISKMANAGEMENT = "RiskManagement";


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
        $this->redirectToShopwareCheckoutFailedWithError($controller, $this->ERROR_PAYMENT_FAILED);
    }

    /**
     * @param \Enlight_Controller_Action $controller
     * @param $errorSnippetKey
     * @throws \Exception
     */
    protected function redirectToShopwareCheckoutFailedWithError(\Enlight_Controller_Action $controller, $errorSnippetKey)
    {
        Shopware()->Session()->offsetSet('mollieError', $errorSnippetKey);

        $url = Shopware()->Front()->Router()->assemble([
            'controller' => 'checkout',
            'action' => 'confirm'
        ]);

        $controller->redirect($url);
    }

    /**
     * @param \Enlight_Controller_Action $controller
     * @param int $articleID
     * @param string $errorMessage
     * @throws \Exception
     */
    protected function redirectToPDPWithError(\Enlight_Controller_Action $controller, $articleID, $errorMessage)
    {
        Shopware()->Session()->offsetSet('mollieErrorMessage', $errorMessage);

        $url = $controller->Front()->Router()->assemble([
            'controller' => 'detail',
            'action' => 'index',
            'sArticle' => $articleID,
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
