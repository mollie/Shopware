<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_View;
use Exception;
use Mollie\Api\Resources\Method;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Config;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use MollieShopware\Components\Payment\Provider\ActivePaymentMethodsProvider;
use MollieShopware\MollieShopware;
use Psr\Log\LoggerInterface;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentMethodSubscriber implements SubscriberInterface
{
    /** @var ActivePaymentMethodsProvider  */
    private $activePaymentMethodsProvider;

    /** @var Basket */
    private $basket;

    /** @var ContainerInterface */
    private $container;

    /** @var LoggerInterface */
    private $logger;

    /** @var MollieShopSwitcher */
    private $mollieShopSwitcher;

    /**
     * @param ActivePaymentMethodsProvider $activePaymentMethodsProvider
     * @param Basket $basket
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param MollieShopSwitcher $mollieShopSwitcher
     */
    public function __construct(
        ActivePaymentMethodsProvider $activePaymentMethodsProvider,
        Basket $basket,
        ContainerInterface $container,
        LoggerInterface $logger,
        MollieShopSwitcher $mollieShopSwitcher
    ) {
        $this->activePaymentMethodsProvider = $activePaymentMethodsProvider;
        $this->basket = $basket;
        $this->container = $container;
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
     * @param Enlight_Controller_ActionEventArgs $args
     * @throws Exception
     */
    public function onFrontendCheckoutPostDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        $actionName = $args->getRequest()->getActionName();

        if ($actionName !== 'shippingPayment' && $actionName !== 'confirm') {
            return;
        }

        $shop = $this->container->get('shop');
        $config = $shop instanceof Shop ? $this->getConfig($shop) : null;

        if (!$config || !$config->useMolliePaymentMethodLimits()) {
            return;
        }

        $view = $args->getSubject()->View();

        # get the basket amount for the current view
        try {
            $basketAmount = $this->basket->getBasketAmountForView($view);
        } catch (Exception $exception) {
            $this->logger->error('Could not retrieve basket amount for view.');
            return;
        }

        # get all active and available payment methods for a certain amount from Mollie
        $availableMethods = $this->activePaymentMethodsProvider->getActivePaymentMethodsForAmount(
            $basketAmount,
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
     * Returns the configuration for the current shop.
     *
     * @return Config|null
     */
    private function getConfig(Shop $shop)
    {

        return $this->mollieShopSwitcher->getConfig($shop->getId());
    }
}
