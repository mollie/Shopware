<?php

namespace MollieShopware\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_View;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandler;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectSetup;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use MollieShopware\Components\Logger;
use Shopware\Components\Theme\LessDefinition;

class ApplePayDirectSubscriber implements SubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            'Theme_Compiler_Collect_Plugin_Javascript' => 'onCollectJavascript',
            'Theme_Compiler_Collect_Plugin_Less' => 'onCollectLess',
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onFrontendPostDispatch',
            'Enlight_Controller_Action_PostDispatch_Widget' => 'onFrontendPostDispatch',
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onFrontendCheckoutPostDispatch',
        ];
    }


    /**
     * @param Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function onCollectJavascript(Enlight_Event_EventArgs $args)
    {
        $collection = new ArrayCollection();
        $collection->add(__DIR__ . '/../Resources/views/frontend/_public/src/js/applepay-direct.js');

        return $collection;
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function onCollectLess(Enlight_Event_EventArgs $args)
    {
        $lessFiles = [];
        $lessFiles[] = __DIR__ . '/../Resources/views/frontend/_public/src/less/applepay-buttons.less';

        $less = new LessDefinition(
            [], // configuration
            $lessFiles, // less files to compile
            __DIR__
        );

        return new ArrayCollection([$less]);
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Request_Request $request */
        $request = $args->getRequest();

        /** @var Enlight_View $view */
        $view = $args->getSubject()->View();

        $buttonBuilder = Shopware()->Container()->get('mollie_shopware.components.apple_pay_direct.services.button_builder');

        # add the apple pay direct data for our current view.
        # the data depends on our page.
        # this might either be a product on PDP, or the full cart data
        $buttonBuilder->addButtonStatus(
            $request,
            $view,
            Shopware()->Shop()
        );

    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendCheckoutPostDispatch(Enlight_Event_EventArgs $args)
    {
        $actionName = $args->getRequest()->getActionName();

        if ($actionName !== 'shippingPayment' && $actionName !== 'confirm') {
            return;
        }

        /** @var Enlight_View $view */
        $view = $args->getSubject()->View();

        if ($actionName === 'confirm') {
            $this->preventApplePayDirectAsPaymentMethodOnConfirm($view);
            return;
        }

        $sPayments = $view->getAssign('sPayments');
        if ($sPayments === null) {
            return;
        }

        $this->removeApplePayDirectFromPaymentMeans($sPayments);

        $view->assign('sPayments', $sPayments);
    }

    /**
     * Remove "Apple Pay Direct" payment method from sPayments to avoid
     * that a user will be able to choose this payment method in the checkout
     *
     * @param array $sPayments
     */
    private function removeApplePayDirectFromPaymentMeans(array &$sPayments)
    {
        foreach ($sPayments as $index => $payment) {
            if ($payment['name'] === ShopwarePaymentMethod::APPLEPAYDIRECT) {
                unset($sPayments[$index]);
                break;
            }
        }
    }

    /**
     * @param Enlight_View $view
     */
    private function preventApplePayDirectAsPaymentMethodOnConfirm(Enlight_View $view)
    {
        $payment = null;

        $admin = Shopware()->Modules()->Admin();
        $session = Shopware()->Session();

        if (!empty($view->sUserData['additional']['payment'])) {
            $payment = $view->sUserData['additional']['payment'];
        } elseif (!empty($session['sPaymentID'])) {
            $payment = $admin->sGetPaymentMeanById($session['sPaymentID'], $view->sUserData);
        }

        if ($payment && $payment['name'] === 'mollie_' . PaymentMethod::APPLEPAY_DIRECT) {
            $payment = $admin->sGetPaymentMean('mollie_' . PaymentMethod::APPLE_PAY);
            $view->assign('sPayment', $payment);
        }
    }
}
