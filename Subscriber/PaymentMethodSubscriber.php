<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_View;
use Exception;
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
     * @throws Exception
     */
    public function onFrontendCheckoutPostDispatch(Enlight_Event_EventArgs $args)
    {
        $shop = Shopware()->Shop();
        $actionName = $args->getRequest()->getActionName();
        $config = $shop ? $this->mollieShopSwitcher->getConfig($shop->getId()) : null;

        if (!$config || !$config->useMolliePaymentMethodLimits()) {
            return;
        }

        if ($actionName !== 'shippingPayment' && $actionName !== 'confirm') {
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

        # get all active and available payment methods for a certain amount from Mollie
        $availableMethods = $this->getActivePaymentMethodsProvider($config)->getActivePaymentMethodsFromMollie(
            [
                'amount' => [
                    'value' => number_format($value, 2),
                    'currency' => $currency,
                ]
            ],
            [$shop]
        );

        # remove unavailable payment methods from the checkout view
        $this->removeUnavailablePaymentMethods($view, $availableMethods);
    }

    /**
     * @param Enlight_View $view
     * @param array $paymentMethods
     * @return void
     */
    public function removeUnavailablePaymentMethods(Enlight_View $view, array $paymentMethods)
    {
        # filter payment methods
        $paymentMethods = $this->filterSupportedMethods($paymentMethods);
        $paymentMethodIds = $this->filterIdsFromPaymentMethodArray($paymentMethods);

        if (empty($paymentMethodIds)) {
            return;
        }

        # get all payment methods assigned to the checkout
        $sPayments = $view->getAssign('sPayments');

        if (!is_array($sPayments)) {
            return;
        }

        foreach ($sPayments as $index => $payment) {
            # skip payment method if it's not a Mollie payment method
            if (strpos($payment['name'], MollieShopware::PAYMENT_PREFIX) === false) {
                continue;
            }

            # get the id of the payment method without the prefix
            $id = substr($payment['name'], strlen(MollieShopware::PAYMENT_PREFIX));

            # remove the payment method if it's not part of the available payment methods
            if (!in_array($id, $paymentMethodIds, true)) {
                unset($sPayments[$index]);
            }
        }

        $view->assign('sPayments', $sPayments);
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
    private function filterSupportedMethods(array $paymentMethods)
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
