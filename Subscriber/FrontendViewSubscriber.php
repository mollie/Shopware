<?php

	// Mollie Shopware Plugin Version: 1.3.9.1

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;

class FrontendViewSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend' => 'addViewDirectory',
            'enlight_controller_action_predispatch_frontend_checkout'=>'getController',
        ];
    }

    /**
     * Add plugin view dir to Smarty
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function addViewDirectory(Enlight_Event_EventArgs $args)
    {

        $controller = $args->getSubject();
        $view = $controller->View();

        $view->addTemplateDir(__DIR__ . '/../Views');

    }

    public function getController(Enlight_Event_EventArgs $args)
    {

        $session = Shopware()->Session();

        if ($session->mollieError || $session->mollieStatusError){

            $controller = $args->getSubject();

            $view = $controller->view();

            $view->sMollieError = $session->mollieError;
            $view->sMollieStatusError = $session->mollieStatusError;

            // unset error, so it wont show up on next page view
            $session->mollieStatusError = $session->mollieError = null;

        }


    }
}
