<?php

namespace MollieShopware\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Event_EventArgs;
use Enlight_View;
use MollieShopware\Components\Config;
use Shopware\Components\Theme\LessDefinition;

class FrontendViewSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch' => 'addComponentsVariables',
            'Enlight_Controller_Action_PreDispatch_Frontend' => 'addViewDirectory',
            'Enlight_Controller_Action_PreDispatch_Frontend_Checkout' => 'getController',
            'Enlight_Controller_Action_PreDispatch_Frontend_Detail' => 'getController',
            'Theme_Compiler_Collect_Plugin_Javascript' => 'onCollectJavascript',
            'Theme_Compiler_Collect_Plugin_Less' => 'onCollectLess',
        ];
    }

    /**
     * Add plugin view dir to Smarty
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function addComponentsVariables(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = null;

        /** @var null|string $controllerName */
        $controllerName = null;

        /** @var Enlight_View $view */
        $view = null;

        if (method_exists($args, 'getSubject')) {
            $controller = $args->getSubject();
            $controllerName = $controller->Request()->getControllerName();
        }

        if ($controller !== null) {
            $view = $controller->View();
        }

        /** @var Config $config */
        $config = Shopware()->Container()->get('mollie_shopware.config');

        if ($controllerName === 'checkout' && $config !== null && $view !== null) {
            $view->assign('sMollieEnableComponent', $config->enableCreditCardComponent());
            $view->assign('sMollieEnableComponentStyling', $config->enableCreditCardComponentStyling());
        }
    }

    /**
     * Add plugin view dir to Smarty
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function addViewDirectory(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = null;

        /** @var Enlight_View $view */
        $view = null;

        if (method_exists($args, 'getSubject')) {
            $controller = $args->getSubject();
        }

        if ($controller !== null) {
            $view = $controller->View();
        }

        if ($view !== null) {
            $view->addTemplateDir(__DIR__ . '/../Resources/views');
        }
    }

    /**
     * Get error messages from session and assign them to the frontend view
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function getController(Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Components_Session_Namespace $session */
        $session = Shopware()->Session();

        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var Enlight_View $view */
        $view = null;

        if (!empty($controller)) {
            $view = $controller->view();
        }

        if ($session !== null && $view !== null && ($session->offsetGet('mollieError') || $session->offsetGet('mollieStatusError'))) {

            // assign errors to view
            $view->assign('sMollieError', $session->offsetGet('mollieError'));
            $view->assign('sMollieStatusError', $session->offsetGet('mollieStatusError'));

            // unset error, so it wont show up on next page view
            $session->mollieStatusError = null;
            $session->mollieError = null;
        }

        # assigned already translated texts to the view
        if ($session !== null && $view !== null && $session->offsetGet('mollieErrorMessage')) {
            // assign errors to view
            $view->assign('sMollieErrorMessage', $session->offsetGet('mollieErrorMessage'));
            // unset error, so it won't show up on next page view
            $session->mollieErrorMessage = null;
        }
    }

    /**
     * Collects javascript files.
     *
     * @param Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function onCollectJavascript(Enlight_Event_EventArgs $args)
    {
        // Create new array collection to add src files
        $collection = new ArrayCollection();

        # this is used to hide the plain Apple Pay too, if not available for the user
        $collection->add(__DIR__ . '/../Resources/views/frontend/_public/src/js/applepay.js');

        return $collection;
    }

    /**
     * Collects Less files
     *
     * @param Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function onCollectLess(Enlight_Event_EventArgs $args)
    {
        $lessFiles = [];
        $lessFiles[] = __DIR__ . '/../Resources/views/frontend/_public/src/less/checkout.less';
        $lessFiles[] = __DIR__ . '/../Resources/views/frontend/_public/src/less/components.less';

        $less = new LessDefinition(
            [], // configuration
            $lessFiles, // less files to compile
            __DIR__
        );

        return new ArrayCollection([$less]);
    }
}
