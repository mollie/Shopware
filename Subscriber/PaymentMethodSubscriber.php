<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_View;
use Mollie\Api\Resources\Method;
use MollieShopware\Components\Config;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use MollieShopware\Components\Payment\Provider\ActivePaymentMethodsProvider;
use MollieShopware\MollieShopware;
use Psr\Log\LoggerInterface;

class PaymentMethodSubscriber implements SubscriberInterface
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * @var MollieShopSwitcher
     */
    private $mollieShopSwitcher;

    /**
     * @param LoggerInterface $logger
     * @param MollieShopSwitcher $mollieShopSwitcher
     */
    public function __construct(LoggerInterface $logger, MollieShopSwitcher $mollieShopSwitcher)
    {
        $this->logger = $logger;
        $this->mollieShopSwitcher = $mollieShopSwitcher;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onFrontendCheckoutPostDispatch',
        ];
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @throws \Exception
     */
    public function onFrontendCheckoutPostDispatch(\Enlight_Event_EventArgs $args)
    {
        $actionName = $args->getRequest()->getActionName();
        $config = $this->mollieShopSwitcher->getConfig(Shopware()->Shop()->getId());

        if ($actionName !== 'shippingPayment' && $actionName !== 'confirm' && $config->isTestmodeActive() !== false) {
            return;
        }

        /** @var Enlight_View $view */
        $view = $args->getSubject()->View();
        $basket = isset($view->sBasket) && is_array($view->sBasket) ? $view->sBasket : [];
        $value = isset($basket['AmountNumeric']) ? $basket['AmountNumeric'] : '';
        $currency = isset($basket['sCurrencyName']) ? $basket['sCurrencyName'] : '';

        if ($value === '' || $currency === '') {
            return;
        }

        $availableMethods = $this->getActivePaymentMethodsProvider($config)->getActivePaymentMethodsFromMollie(
            number_format($value, 2),
            $currency,
            [Shopware()->Shop()]
        );

        $availableMethods = $this->filterSupportedMethod($availableMethods);

        if (empty($availableMethods)) {
            return;
        }

        # remove our voucher if no valid product
        # has been found in the current cart.
        $sPayments = $view->getAssign('sPayments');
        $sPayments = $this->removeUnavailablePaymentMethods(
            $this->filterIdsFromPaymentMethodArray($availableMethods),
            $sPayments
        );

        $view->assign('sPayments', $sPayments);
    }

    /**
     * @param array $availablePaymentMethodIds
     * @param array|null $sPayments
     * @return array|null
     */
    private function removeUnavailablePaymentMethods($availablePaymentMethodIds, $sPayments)
    {
        if ($sPayments === null) {
            return $sPayments;
        }

        foreach ($sPayments as $index => $payment) {
            $id = substr($payment['name'], strlen(MollieShopware::PAYMENT_PREFIX));

            if (!in_array($id, $availablePaymentMethodIds, true)) {
                unset($sPayments[$index]);
                break;
            }
        }

        return $sPayments;
    }

    /**
     * @param array $paymentMethods
     * @return array
     */
    private function filterIdsFromPaymentMethodArray(array $paymentMethods)
    {
        $ids = [];

        /** @var Method $method */
        foreach ($paymentMethods as $method) {
            $ids[] = $method->id;
        }

        return $ids;
    }

    /**
     * @param array $paymentMethods
     * @return array
     */
    private function filterSupportedMethod(array $paymentMethods)
    {
        return array_filter($paymentMethods, static function (Method $paymentMethod) {
            return in_array($paymentMethod->id, PaymentMethodsInstaller::getSupportedPaymentMethods(), true);
        });
    }

    /**
     * @return ActivePaymentMethodsProvider
     */
    private function getActivePaymentMethodsProvider(Config $config)
    {
        return new ActivePaymentMethodsProvider($config, $this->logger);
    }
}
