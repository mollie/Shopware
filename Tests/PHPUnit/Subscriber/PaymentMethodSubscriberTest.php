<?php

namespace MollieShopware\Tests\PHPUnit\Subscriber;

use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_Request;
use Enlight_Template_Manager;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use MollieShopware\Components\Basket\Basket;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Payment\Provider\ActivePaymentMethodsProvider;
use MollieShopware\MollieShopware;
use MollieShopware\Subscriber\PaymentMethodSubscriber;
use MollieShopware\Tests\PHPUnit\Utils\Fakes\View\FakeView;
use MollieShopware\Tests\Utils\Fakes\Config\FakeConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentMethodSubscriberTest extends TestCase
{
    const SHOPWARE_PAYMENT_METHOD_NAME = 'debit';

    /**
     * Returns a new instance of the active payment methods provider.
     *
     * @param array $methods
     * @return ActivePaymentMethodsProvider|MockObject
     */
    public function setUpActivePaymentMethodsProvider(array $methods)
    {
        return $this->createConfiguredMock(ActivePaymentMethodsProvider::class, [
           'getActivePaymentMethodsForAmount' => $methods,
        ]);
    }

    /**
     * Returns an array of Mollie payment method objects.
     *
     * @param array $methods
     * @return array
     */
    public function setUpAvailableMethods(array $methods)
    {
        $client = $this->createMock(MollieApiClient::class);

        $methodObjects = [];

        foreach ($methods as $method) {
            $methodObject = new Method($client);
            $methodObject->id = $method;

            $methodObjects[] = $methodObject;
        }

        return $methodObjects;
    }

    /**
     * Returns subscriber args.
     *
     * @param FakeView $view
     *
     * @return Enlight_Controller_ActionEventArgs|MockObject
     */
    public function setUpArgs(FakeView $view)
    {
        $request = $this->createConfiguredMock(Enlight_Controller_Request_Request::class, [
            'getActionName' => 'confirm',
        ]);

        $subject = $this->createConfiguredMock(Enlight_Controller_Action::class, [
            'View' => $view,
        ]);

        return $this->createConfiguredMock(Enlight_Controller_ActionEventArgs::class, [
            'getRequest' => $request,
            'getSubject' => $subject,
        ]);
    }

    /**
     * Returns a new instance of the payment method subscriber.
     *
     * @param array $available
     * @return PaymentMethodSubscriber
     */
    public function setUpSubscriber(array $available)
    {
        $basket = new Basket($this->createMock(ModelManager::class), new NullLogger());

        $shop = $this->createConfiguredMock(Shop::class, [
            'getId' => 1,
        ]);

        $container = $this->createConfiguredMock(ContainerInterface::class, [
            'get' => $shop,
        ]);

        $config = new FakeConfig(false);
        $config->setUseMolliePaymentMethodLimits(true);

        $mollieShopSwitcher = $this->createConfiguredMock(MollieShopSwitcher::class, [
            'getConfig' => $config,
        ]);

        return new PaymentMethodSubscriber(
            $this->setUpActivePaymentMethodsProvider($available),
            $basket,
            $container,
            new NullLogger(),
            $mollieShopSwitcher
        );
    }

    /**
     * Returns a view with assigned payment methods.
     *
     * @return FakeView
     */
    public function setUpView()
    {
        $view = new FakeView($this->createMock(Enlight_Template_Manager::class));

        $view->assign('sBasket', [
            'AmountNumeric' => 100.0,
            'sCurrencyName' => 'EUR',
        ]);

        $view->assign('sPayments', [
            [
                'name' => self::SHOPWARE_PAYMENT_METHOD_NAME,
            ],
            [
                'name' => MollieShopware::PAYMENT_PREFIX . PaymentMethod::CREDITCARD,
            ],
            [
                'name' => MollieShopware::PAYMENT_PREFIX . PaymentMethod::IDEAL,
            ],
            [
                'name' => MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_LATER,
            ],
            [
                'name' => MollieShopware::PAYMENT_PREFIX . PaymentMethod::PAYPAL,
            ]
        ]);

        return $view;
    }

    /**
     * This test verifies that our required subscribers
     * are not changed without recognizing it.
     *
     * @testdox Method getSubscribedEvents returns the expected events.
     */
    public function testSubscribedEvents()
    {
        $expected = [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout',
        ];

        $this->assertEquals($expected, array_keys(PaymentMethodSubscriber::getSubscribedEvents()));
    }

    /**
     * This test verifies if payment methods that are unavailable
     * are removed from the checkout view.
     *
     * @testdox Method removeUnavailablePaymentMethods removes the expected payment methods from view.
     */
    public function testRemovesPaymentMethods()
    {
        $available = $this->setUpAvailableMethods(['ideal', 'paypal']);
        $paymentMethodSubscriber = $this->setUpSubscriber($available);
        $view = $this->setUpView();
        $args = $this->setUpArgs($view);

        $paymentMethodSubscriber->onFrontendCheckoutPostDispatch($args);

        $actualPaymentMethods = [];

        foreach ($view->getAssign('sPayments') as $paymentMethod) {
            $actualPaymentMethods[] = $paymentMethod['name'];
        }

        $expectedPaymentMethods = [
            self::SHOPWARE_PAYMENT_METHOD_NAME,
            MollieShopware::PAYMENT_PREFIX . PaymentMethod::IDEAL,
            MollieShopware::PAYMENT_PREFIX . PaymentMethod::PAYPAL,
        ];

        sort($actualPaymentMethods);
        sort($expectedPaymentMethods);

        self::assertEquals($expectedPaymentMethods, $actualPaymentMethods);
    }
}
