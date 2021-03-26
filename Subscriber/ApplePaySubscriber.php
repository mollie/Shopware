<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandler;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectSetup;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use Shopware_Components_Modules;
use Throwable;

class ApplePaySubscriber implements SubscriberInterface
{
    /**
     * @var \Shopware_Components_Modules
     */
    private $modules;

    /**
     * @var Account $accountService
     */
    private $accountService;

    /**
     * @var ApplePayPaymentMethod
     */
    private $paymentMethodService;

    /**
     * @param Shopware_Components_Modules|null $modules
     */
    public function __construct(Account $accountService, ApplePayPaymentMethod $paymentMethodService, $modules)
    {
        $this->accountService = $accountService;
        $this->paymentMethodService = $paymentMethodService;
        $this->modules = $modules;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend_Checkout' => 'onFrontendCheckoutPreDispatch',
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onFrontendAccountPostDispatch',
        ];
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendAccountPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->get('request');

        if ($request->getActionName() === 'payment') {
            $view = $args->getSubject()->View();
            $paymentMeans = $this->removeApplePaymentMethodsFromPaymentMeans($view->sPaymentMeans);
            $view->sPaymentMeans = $paymentMeans;
        }

        if ($request->getActionName() !== 'login') {
            return;
        }

        if ($this->modules->Admin()->sCheckUser() === false) {
            return;
        }

        $currentPaymentMethod = $this->modules->Admin()->sGetUserData()['additional']['payment']['name'];

        try {
            if ($this->paymentMethodService->isApplePayPaymentMethod($currentPaymentMethod) === false) {
                return;
            }

            $userId = (int)$this->modules->Admin()->sGetUserData()['additional']['user']['id'];
            $paymentId = $this->accountService->getCustomerDefaultNonApplePayPaymentMethod($userId);

            $this->accountService->updateCustomerDefaultPaymentMethod($userId, $paymentId);
        } catch (Throwable $e) {
            // prevent login to break if something goes wrong here
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendCheckoutPreDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->get('request');

        if ($request->getActionName() !== 'finish') {
            return;
        }

        $currentPaymentMethod = $this->modules->Admin()->sGetUserData()['additional']['payment']['name'];

        try {
            if ($this->paymentMethodService->isApplePayPaymentMethod($currentPaymentMethod) === false) {
                return;
            }

            $userId = (int)$this->modules->Admin()->sGetUserData()['additional']['user']['id'];
            $paymentId = $this->accountService->getCustomerDefaultNonApplePayPaymentMethod($userId);

            $this->accountService->updateCustomerDefaultPaymentMethod($userId, $paymentId);
        } catch (Throwable $e) {
            // prevent checkout finish to break if something goes wrong here
        }
    }

    /**
     * @param array $paymentMeans
     * @return array
     */
    private function removeApplePaymentMethodsFromPaymentMeans(array $paymentMeans)
    {
        return array_filter($paymentMeans, function ($item) {
            if (!in_array($item['name'], [ShopwarePaymentMethod::APPLEPAYDIRECT, ShopwarePaymentMethod::APPLEPAY])) {
                return true;
            }
        });
    }
}
