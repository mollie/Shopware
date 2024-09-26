<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Services;

use Enlight_Controller_Request_Request;
use Enlight_Template_Manager;
use Enlight_View_Default;
use Exception;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\Admin\AdminFactoryInterface;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayButtonBuilder;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayDirectDisplayOptions;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\Basket\BasketFactoryInterface;
use MollieShopware\Components\Config;
use MollieShopware\Tests\PHPUnit\Utils\Fakes\View\FakeView;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use sAdmin;
use sBasket;
use Shopware\Models\Shop\Currency;
use Shopware\Models\Shop\Shop;
use Shopware_Components_Config;

class ApplePayButtonBuilderTest extends TestCase
{
    /**
     * @var Account|MockObject
     */
    private $accountService;

    /**
     * @var Config
     */
    private $mollieConfig;

    /**
     * @var ApplePayPaymentMethod|MockObject
     */
    private $applePayPaymentMethod;

    /**
     * @var ApplePayDirectDisplayOptions|MockObject
     */
    private $restrictionService;

    /**
     * @var MockObject|sBasket
     */
    private $sBasket;

    /**
     * @var Enlight_View_Default
     */
    private $view;

    /**
     * @var Enlight_Controller_Request_Request
     */
    private $request;

    /**
     * @var MockObject|Shop
     */
    private $shop;

    /**
     * @var ApplePayButtonBuilder
     */
    private $buttonBuilder;

    public function setUp(): void
    {
        $this->accountService = $this->createMock(Account::class);
        $this->mollieConfig = $this->createMock(Config::class);
        $this->sBasket = $this->createMock(sBasket::class);
        $this->view = new FakeView($this->createMock(Enlight_Template_Manager::class));
        $this->request = $this->createMock(Enlight_Controller_Request_Request::class);

        $this->setUpShop();
        $this->setUpRestrictionService();
        $this->setUpApplePayPaymentMethod();

        $this->buttonBuilder = new ApplePayButtonBuilder(
            $this->accountService,
            $this->mollieConfig,
            $this->createMock(Shopware_Components_Config::class),
            $this->applePayPaymentMethod,
            $this->restrictionService,
            $this->createConfiguredMock(AdminFactoryInterface::class, [
                'create' => $this->getAdminModuleMock(),
            ]),
            $this->createConfiguredMock(BasketFactoryInterface::class, [
                'create' => $this->sBasket,
            ])
        );
    }

    /**
     * @test
     * @testdox Apple Pay direct button is not active when restricted by risk management.
     *
     * @throws Exception
     * @return void
     */
    public function testNotActiveIfRiskManagementBlocked()
    {
        $this->setUpBlockedByRiskManagement();

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertFalse($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is not active when customer not logged in and an ESD product in the basket.
     *
     * @throws Exception
     * @return void
     */
    public function testNotActiveIfNotLoggedInHasEsd(): void
    {
        $this->setUserLoggedIn(false);
        $this->setUpEsdBasket();

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertFalse($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is not active when customer logged in with guest account and an ESD product in the basket.
     *
     * @throws Exception
     * @return void
     */
    public function testNotActiveIfGuestHasEsd(): void
    {
        $this->setUserLoggedIn();
        $this->setUserLoggedInAsGuest();
        $this->setUpEsdBasket();

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertFalse($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is active when customer logged in with full account and an ESD product in the basket.
     *
     * @throws Exception
     * @return void
     */
    public function testActiveIfLoggedInHasEsd(): void
    {
        $this->setUserLoggedIn();
        $this->setUserLoggedInAsGuest(false);
        $this->setUpEsdBasket();

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertTrue($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is active when customer not logged in and no ESD product in the basket.
     *
     * @throws Exception
     * @return void
     */
    public function testActiveIfNotLoggedInHasNoEsd(): void
    {
        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertTrue($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is not active when customer not logged in and PDP is an ESD product.
     *
     * @throws Exception
     * @return void
     */
    public function testNotActiveIfNotLoggedInPdpIsEsd(): void
    {
        $this->setUserLoggedIn(false);
        $this->setUserLoggedInAsGuest(false);
        $this->setUpPdp(true);

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertFalse($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is not active when customer logged in with guest account and PDP is an ESD product.
     *
     * @throws Exception
     * @return void
     */
    public function testNotActiveIfGuestPdpIsEsd(): void
    {
        $this->setUserLoggedIn();
        $this->setUserLoggedInAsGuest();
        $this->setUpPdp(true);

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertFalse($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is active when customer logged in with full account and PDP is an ESD product.
     *
     * @throws Exception
     * @return void
     */
    public function testActiveIfLoggedInPdpIsEsd(): void
    {
        $this->setUserLoggedIn();
        $this->setUserLoggedInAsGuest(false);
        $this->setUpPdp(true);

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertTrue($result['active']);
    }

    /**
     * @test
     * @testdox Apple Pay direct button is active when customer not logged in and PDP is no ESD product.
     *
     * @throws Exception
     * @return void
     */
    public function testButtonActiveIfNotLoggedInPdpIsNoEsd(): void
    {
        $this->setUpPdp();

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertTrue($result['active']);
    }

    /**
     * Returns a preconfigured mock for the shop model.
     *
     * @param string $currency
     * @return void
     */
    private function setUpShop(string $currency = 'EUR'): void
    {
        $this->shop = $this->createConfiguredMock(Shop::class, [
            'getCurrency' => $this->createConfiguredMock(Currency::class, [
                'getCurrency' => strtoupper($currency),
            ])
        ]);
    }

    /**
     * Creates a mock for the Apple Pay payment method object.
     * Sets method isApplePayDirectEnabled to true.
     *
     * @return void
     */
    private function setUpApplePayPaymentMethod(): void
    {
        $this->applePayPaymentMethod = $this->createMock(ApplePayPaymentMethod::class);

        $this->applePayPaymentMethod
            ->expects(self::once())
            ->method('isApplePayDirectEnabled')
            ->willReturn(true);
    }

    /**
     * Creates a mock for the Apple Pay payment method object.
     * Sets method isApplePayDirectEnabled to true.
     *
     * @return void
     */
    private function setUpRestrictionService(): void
    {
        $this->restrictionService = $this->createConfiguredMock(ApplePayDirectDisplayOptions::class, [
            'getDisplayOptions' => [],
        ]);
    }

    /**
     * Creates the test scenario for Apple Pay risk management.
     *
     * @return void
     */
    private function setUpBlockedByRiskManagement(): void
    {
        $this->applePayPaymentMethod
            ->expects(self::once())
            ->method('isRiskManagementBlocked')
            ->willReturn(true);
    }

    /**
     * Creates the test scenario for a basket with ESD products.
     *
     * @return void
     */
    private function setUpEsdBasket(): void
    {
        $this->request
            ->expects(self::once())
            ->method('getControllerName')
            ->willReturn('checkout');

        $this->sBasket
            ->expects(self::once())
            ->method('sCheckForESD')
            ->willReturn(true);
    }

    /**
     * Creates the test scenario for a PDP for an ESD product.
     *
     * @param bool $isESD
     * @return void
     */
    private function setUpPdp(bool $isESD = false): void
    {
        $this->request
            ->expects(self::once())
            ->method('getControllerName')
            ->willReturn('detail');

        $this->view->assign('sArticle', [
            'ordernumber' => '12345',
            'esd' => $isESD,
        ]);
    }

    /**
     * Creates a test scenario where the user is logged in.
     *
     * @param bool $loggedIn
     * @return void
     */
    private function setUserLoggedIn(bool $loggedIn = true): void
    {
        $this->accountService
            ->expects(self::any())
            ->method('isLoggedIn')
            ->willReturn($loggedIn);
    }

    /**
     * Creates a test scenario where the user is a guest user.
     *
     * @param bool $loggedIn
     * @return void
     */
    private function setUserLoggedInAsGuest(bool $loggedIn = true): void
    {
        $this->accountService
            ->expects(self::any())
            ->method('isLoggedInAsGuest')
            ->willReturn($loggedIn);
    }

    /**
     * Returns a preconfigured mock for the sAdmin module.
     *
     * @return sAdmin
     */
    private function getAdminModuleMock(): sAdmin
    {
        return $this->createConfiguredMock(sAdmin::class, [
            'sGetCountryList' => [
                ['iso' => 'de'],
            ],
        ]);
    }
}
