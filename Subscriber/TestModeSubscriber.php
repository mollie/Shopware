<?php


namespace MollieShopware\Subscriber;


use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_View;
use MollieShopware\Components\Config;
use MollieShopware\MollieShopware;

class TestModeSubscriber implements SubscriberInterface
{

    const TEST_SUFFIX = "(Mollie Test Mode)";

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onFrontendPostDispatch',
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onFrontendPostDispatch',
        ];
    }


    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var Config $config */
        $config = Shopware()->Container()->get('mollie_shopware.config');

        if ($config->isTestmodeActive() === false) {
            return;
        }

        /** @var Enlight_View $view */
        $view = $args->getSubject()->View();

        $ctrl = $args->getRequest()->getControllerName();
        $action = $args->getRequest()->getActionName();

        $prefix = MollieShopware::PAYMENT_PREFIX;

        if ($ctrl === 'account' && $action === 'payment') {
            $this->onAccountPaymentSelectionPostDispatch($view, $prefix);
            return;
        }

        if ($ctrl === 'checkout' && $action === 'shippingPayment') {
            $this->onCheckoutPaymentSelectionPostDispatch($view, $prefix);
            return;
        }

        if ($ctrl === 'checkout' && $action === 'confirm') {
            $this->onCheckoutConfirmPostDispatch($view, $prefix);
            return;
        }

        if ($ctrl === 'checkout' && $action === 'finish') {
            $this->onCheckoutFinishedPostDispatch($view, $prefix);
            return;
        }
    }


    /**
     * @param Enlight_View $view
     * @param $prefix
     */
    public function onAccountPaymentSelectionPostDispatch(Enlight_View $view, $prefix)
    {
        $payments = $view->getAssign('sPaymentMeans');

        if ($payments === null) {
            return;
        }

        foreach ($payments as &$payment) {
            $payment = $this->replacePaymentMeanTitle($payment, $prefix);
        }

        $view->assign('sPaymentMeans', $payments);
    }

    /**
     * @param Enlight_View $view
     * @param $prefix
     */
    public function onCheckoutPaymentSelectionPostDispatch(Enlight_View $view, $prefix)
    {
        $payments = $view->getAssign('sPayments');

        if ($payments === null) {
            return;
        }

        foreach ($payments as &$payment) {
            $payment = $this->replacePaymentMeanTitle($payment, $prefix);
        }

        $view->assign('sPayments', $payments);
    }

    /**
     * @param Enlight_View $view
     * @param $prefix
     */
    public function onCheckoutConfirmPostDispatch(Enlight_View $view, $prefix)
    {
        $sUserData = $view->getAssign('sUserData');

        if (!isset($sUserData['additional'])) {
            return;
        }

        if (!isset($sUserData['additional']['payment'])) {
            return;
        }

        if (!isset($sUserData['additional']['payment']['name'])) {
            return;
        }

        # check if the name contains mollie_
        if (strpos($sUserData['additional']['payment']['name'], $prefix) === false) {
            return;
        }

        $sUserData['additional']['payment']['description'] .= ' ' . self::TEST_SUFFIX;

        $view->assign('sUserData', $sUserData);
    }

    /**
     * @param Enlight_View $view
     * @param $prefix
     */
    public function onCheckoutFinishedPostDispatch(Enlight_View $view, $prefix)
    {
        $payment = $view->getAssign('sPayment');

        $payment = $this->replacePaymentMeanTitle($payment, $prefix);

        $view->assign('sPayment', $payment);
    }

    /**
     * @param $paymentMean
     * @param $prefix
     * @return mixed
     */
    private function replacePaymentMeanTitle($paymentMean, $prefix)
    {
        if (!isset($paymentMean['name'])) {
            return $paymentMean;
        }

        if (!isset($paymentMean['description'])) {
            return $paymentMean;
        }

        if (strpos($paymentMean['name'], $prefix) === false) {
            return $paymentMean;
        }

        $paymentMean['description'] = $paymentMean['description'] . ' ' . self::TEST_SUFFIX;

        return $paymentMean;
    }

}
