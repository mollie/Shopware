<?php

// Mollie Shopware Plugin Version: 1.4.7

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;

class FrontendViewSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend' => 'addViewDirectory',
            'Enlight_Controller_Action_PreDispatch_Frontend_Checkout'=>'getController',
        ];
    }

    /**
     * Add plugin view dir to Smarty
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function addViewDirectory(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var \Enlight_View $view */
        $view = null;

        if (!empty($controller))
            $view = $controller->View();

        if (!empty($view))
            $view->addTemplateDir(__DIR__ . '/../Resources/views');
    }

    /**
     * Get error messages from session and assign them to the frontend view
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function getController(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Components_Session_Namespace $session */
        $session = Shopware()->Session();

        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var \Enlight_View $view */
        $view = null;

        if (!empty($controller))
            $view = $controller->view();

        if ($session !== null && $view !== null &&
            ($session->mollieError || $session->mollieStatusError)) {

            // assign errors to view
            $view->assign('sMollieError', $session->mollieError);
            $view->assign('sMollieStatusError', $session->mollieStatusError);

            // unset error, so it wont show up on next page view
            $session->mollieStatusError = $session->mollieError = null;
        }
    }
}
