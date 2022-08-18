<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Services;

use Enlight_Controller_Request_Request;
use Enlight_Template_Manager;
use Enlight_View_Default;
use Exception;
use MollieShopware\Components\Account\Account;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayButtonBuilder;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayDirectDisplayOptions;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayPaymentMethod;
use MollieShopware\Components\Config;
use MollieShopware\Components\ConfigInterface;
use MollieShopware\Tests\PHPUnit\Utils\Fakes\View\FakeView;
use MollieShopware\Tests\Utils\Fakes\Config\FakeConfig;
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
     * @var Shopware_Components_Config|MockObject
     */
    private $shopwareConfig;

    /**
     * @var ApplePayPaymentMethod|MockObject
     */
    private $applePayPaymentMethod;

    /**
     * @var ApplePayDirectDisplayOptions|MockObject
     */
    private $restrictionService;

    /**
     * @var sAdmin|MockObject
     */
    private $sAdmin;

    /**
     * @var sBasket|MockObject
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
     * @var Shop|MockObject
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
        $this->shopwareConfig = $this->createMock(Shopware_Components_Config::class);
        $this->applePayPaymentMethod = $this->createMock(ApplePayPaymentMethod::class);
        $this->restrictionService = $this->createConfiguredMock(ApplePayDirectDisplayOptions::class, [
            'getDisplayOptions' => [],
        ]);
        $this->sAdmin = $this->createConfiguredMock(sAdmin::class, [
            'sGetCountryList' => [
                ['iso' => 'nl'],
            ],
        ]);
        $this->sBasket = $this->createMock(sBasket::class);
        $this->view = new FakeView($this->createMock(Enlight_Template_Manager::class));
        $this->request = $this->createMock(Enlight_Controller_Request_Request::class);
        $this->shop = $this->createConfiguredMock(Shop::class, [
            'getCurrency' => $this->createConfiguredMock(Currency::class, [
                'getCurrency' => 'EUR',
            ])
        ]);

        $this->buttonBuilder = new ApplePayButtonBuilder(
            $this->accountService,
            $this->mollieConfig,
            $this->shopwareConfig,
            $this->applePayPaymentMethod,
            $this->restrictionService,
            $this->sAdmin,
            $this->sBasket
        );
    }

    public function setUpBlockedByRiskManagement()
    {
//        $this->applePayPaymentMethod = $this->createConfiguredMock(ApplePayPaymentMethod::class, [
//            'isApplePayDirectEnabled' => true,
//            'isRiskManagementBlocked' => true,
//        ]);

        $this->applePayPaymentMethod->expects(self::once())->method('isApplePayDirectEnabled')->willReturn(true);
        $this->applePayPaymentMethod->expects(self::once())->method('isRiskManagementBlocked')->willReturn(true);
    }

    /**
     * @test
     * @testdox Method addButtonStatus() adds the expected status.
     * @return void
     * @throws Exception
     */
    public function testIfApplePayButtonIsNotActiveWhenRestrictedByRiskManagement()
    {
        $this->setUpBlockedByRiskManagement();

        $this->buttonBuilder->addButtonStatus($this->request, $this->view, $this->shop);

        $result = $this->view->getAssign('sMollieApplePayDirectButton');

        self::assertFalse($result['active']);
    }
}