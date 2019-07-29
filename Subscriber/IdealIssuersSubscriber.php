<?php

// Mollie Shopware Plugin Version: 1.4.9

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;

class IdealIssuersSubscriber implements SubscriberInterface
{
    /** @var \MollieShopware\Components\Services\IdealService $idealService */
    protected $idealService;

    public function __construct($idealService)
    {
        /** @var \MollieShopware\Components\Services\IdealService idealService */
        $this->idealService = $idealService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_UpdatePayment_FilterSql' => 'onUpdatePaymentForUser',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onChoosePaymentDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Account' => 'onChoosePaymentDispatch',
        ];
    }

    /**
     * Assign iDeal issuers to the view
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onChoosePaymentDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var \Enlight_View $view */
        $view = $controller->View();

        try {
            $idealIssuers = $this->idealService->getIssuers();

            $view->assign('mollieIdealIssuers', $idealIssuers);
            $view->assign('mollieIssues', false);
        }
        catch(\Exception $ex) {
            $view->assign('mollieIssues', true);
        }

        $view->addTemplateDir(__DIR__ . '/../Resources/views');
    }

    /**
     * When a payment method is changed, the chosen payment method is saved on the user
     * For iDEAL an issuer should also be saved to the database
     *
     * @param \Enlight_Event_EventArgs $args
     * @return mixed $query
     */
    public function onUpdatePaymentForUser(\Enlight_Event_EventArgs $args)
    {
        // get query
        $query = $args->getReturn();

        // get issuer
        $issuer = Shopware()->Front()->Request()->getPost('mollie-ideal-issuer');

        // write issuer id to database
        $this->idealService->setSelectedIssuer($issuer);

        return $query;
    }
}
