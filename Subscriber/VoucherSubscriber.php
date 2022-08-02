<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use Enlight_View;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Models\Voucher\VoucherType;
use MollieShopware\MollieShopware;

class VoucherSubscriber implements SubscriberInterface
{

    /**
     * @var Basket
     */
    private $basket;


    /**
     * @param Basket $basket
     */
    public function __construct(Basket $basket)
    {
        $this->basket = $basket;
    }


    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Account' => 'onFrontendAccountPostDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onFrontendCheckoutPostDispatch',
        ];
    }


    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendAccountPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->get('request');

        if ($request->getActionName() !== 'payment') {
            return;
        }

        $view = $args->getSubject()->View();

        # voucher must never be displayed in the account
        # this is always dependant on the products in the cart
        $view->sPaymentMeans = $this->removeVoucherPayment($view->sPaymentMeans);
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @throws \Exception
     */
    public function onFrontendCheckoutPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->get('request');

        $actionName = $args->getRequest()->getActionName();

        if ($actionName !== 'shippingPayment' && $actionName !== 'confirm') {
            return;
        }

        /** @var Enlight_View $view */
        $view = $args->getSubject()->View();

        $voucherProductInCart = $this->hasCartVoucherProducts();

        if (!$voucherProductInCart) {
            # remove our voucher if no valid product
            # has been found in the current cart.
            $sPayments = $view->getAssign('sPayments');
            $sPayments = $this->removeVoucherPayment($sPayments);
            $view->assign('sPayments', $sPayments);
        }
    }


    /**
     * @throws \Exception
     * @return bool
     */
    private function hasCartVoucherProducts()
    {
        $userData = Shopware()->Session()->sOrderVariables['sUserData'];

        $lines = $this->basket->getMollieBasketLines($userData);

        foreach ($lines as $line) {
            if (VoucherType::isValidVoucher($line->getVoucherType())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $sPayments
     * @return array
     */
    private function removeVoucherPayment($sPayments)
    {
        if ($sPayments === null) {
            return $sPayments;
        }

        foreach ($sPayments as $index => $payment) {
            if ($payment['name'] === MollieShopware::PAYMENT_PREFIX . PaymentMethod::VOUCHERS) {
                unset($sPayments[$index]);
                break;
            }
        }

        return $sPayments;
    }
}
