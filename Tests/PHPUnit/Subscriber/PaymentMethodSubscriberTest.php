<?php

namespace MollieShopware\Tests\PHPUnit\Subscriber;

use Enlight_Template_Manager;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\MollieShopware;
use MollieShopware\Subscriber\PaymentMethodSubscriber;
use MollieShopware\Tests\PHPUnit\Utils\Fakes\View\FakeView;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PaymentMethodSubscriberTest extends TestCase
{
    const SHOPWARE_PAYMENT_METHOD_NAME = 'debit';

    /**
     * Creates an array of Mollie payment method objects.
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
     * Creates a view with assigned payment methods.
     *
     * @return FakeView
     */
    public function setUpView()
    {
        $view = new FakeView($this->createMock(Enlight_Template_Manager::class));

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

        $paymentMethodSubscriber = new PaymentMethodSubscriber(
            $this->createMock(LoggerInterface::class),
            $this->createMock(MollieShopSwitcher::class)
        );

        $view = $this->setUpView();

        $paymentMethodSubscriber->removeUnavailablePaymentMethods(
            $view,
            $available
        );

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
