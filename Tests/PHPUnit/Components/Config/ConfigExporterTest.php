<?php

namespace MollieShopware\Tests\Components\Config;

use Doctrine\ORM\EntityManagerInterface;
use Mollie\Api\Endpoints\ProfileEndpoint;
use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Services\ShopService;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Shopware\Models\Shop\Shop;

class ConfigExporterTest extends TestCase
{
    const TEST_VALUE_SHOP = 'My Shop';
    const TEST_VALUE_TEST_MODE_ACTIVE = false;
    const TEST_VALUE_LOG_LEVEL = Logger::INFO;
    const TEST_VALUE_CREATE_ORDER_BEFORE_PAYMENT = true;
    const TEST_VALUE_PAYMENT_METHODS_TYPE = PaymentMethodType::PAYMENTS_API;
    const TEST_VALUE_AUTHORIZED_PAYMENT_STATUS_ID = Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED;
    const TEST_VALUE_TRANSACTION_NUMBER_TYPE = 'mollie';
    const TEST_VALUE_UPDATE_ORDER_STATUS = false;
    const TEST_VALUE_SHIPPED_STATUS = Status::ORDER_STATE_COMPLETELY_DELIVERED;
    const TEST_VALUE_CANCEL_FAILED_ORDERS = false;
    const TEST_VALUE_RESET_INVOICE_AND_SHIPPING = true;
    const TEST_VALUE_AUTO_RESET_STOCK = true;
    const TEST_VALUE_ORDERS_SHIP_ON_STATUS = Status::ORDER_STATE_READY_FOR_DELIVERY;
    const TEST_VALUE_MOLLIE_PAYMENT_METHOD_LIMITS = true;
    const TEST_VALUE_PAYMENT_STATUS_MAIL_ENABLED = true;
    const TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT = true;
    const TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT_STYLING = false;
    const TEST_VALUE_APPLE_PAY_DIRECT_RESTRICTIONS = [];
    const TEST_VALUE_BANK_TRANSFER_DUE_DATE_DAYS = 7;

    const TEST_VALUE_LIVE_API_KEY_VALIDATION = 'Invalid';
    const TEST_VALUE_TEST_API_KEY_VALIDATION = 'Invalid';

    const TEST_VALUE_STATUS_NAME = 'Status';
    const TEST_VALUE_REDUCE_STOCK_ON_PAYMENT = false;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var ShopService
     */
    private $shopService;

    /**
     * @var Config\ConfigExporter
     */
    private $configExporter;

    public function setUp(): void
    {
        $config = $this->createConfiguredMock(Config::class, [
            'isTestmodeActive' => self::TEST_VALUE_TEST_MODE_ACTIVE,
            'getLogLevel' => self::TEST_VALUE_LOG_LEVEL,
            'createOrderBeforePayment' => self::TEST_VALUE_CREATE_ORDER_BEFORE_PAYMENT,
            'getPaymentMethodsType' => self::TEST_VALUE_PAYMENT_METHODS_TYPE,
            'getAuthorizedPaymentStatusId' => self::TEST_VALUE_AUTHORIZED_PAYMENT_STATUS_ID,
            'getTransactionNumberType' => self::TEST_VALUE_TRANSACTION_NUMBER_TYPE,
            'updateOrderStatus' => self::TEST_VALUE_UPDATE_ORDER_STATUS,
            'getOrdersShipOnStatus' => self::TEST_VALUE_ORDERS_SHIP_ON_STATUS,
            'cancelFailedOrders' => self::TEST_VALUE_CANCEL_FAILED_ORDERS,
            'reduceStockOnPayment' => self::TEST_VALUE_REDUCE_STOCK_ON_PAYMENT,
            'resetInvoiceAndShipping' => self::TEST_VALUE_RESET_INVOICE_AND_SHIPPING,
            'autoResetStock' => self::TEST_VALUE_AUTO_RESET_STOCK,
            'getShippedStatus' => self::TEST_VALUE_SHIPPED_STATUS,
            'useMolliePaymentMethodLimits' => self::TEST_VALUE_MOLLIE_PAYMENT_METHOD_LIMITS,
            'isPaymentStatusMailEnabled' => self::TEST_VALUE_PAYMENT_STATUS_MAIL_ENABLED,
            'enableCreditCardComponent' => self::TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT,
            'enableCreditCardComponentStyling' => self::TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT_STYLING,
            'getApplePayDirectRestrictions' => self::TEST_VALUE_APPLE_PAY_DIRECT_RESTRICTIONS,
            'getBankTransferDueDateDays' => self::TEST_VALUE_BANK_TRANSFER_DUE_DATE_DAYS,
        ]);

        $this->setUpEntityManager();
        $this->setUpApiFactory();

        $this->shopService = $this->createMock(ShopService::class);

        $this->configExporter = new Config\ConfigExporter(
            $config,
            $this->entityManager,
            $this->apiFactory,
            $this->shopService
        );
    }

    /**
     * @return void
     */
    public function setUpEntityManager()
    {
        $status = $this->createConfiguredMock(Status::class, [
            'getName' => self::TEST_VALUE_STATUS_NAME,
        ]);

        $repository = $this->createConfiguredMock(Repository::class, [
            'find' => $status,
        ]);

        $this->entityManager = $this->createConfiguredMock(EntityManagerInterface::class, [
            'getRepository' => $repository,
        ]);
    }

    /**
     * @return void
     */
    public function setUpApiFactory()
    {
        $profilesEndPoint = $this->createConfiguredMock(ProfileEndpoint::class, [
            'getCurrent' => null,
        ]);

        $apiClient = $this->createMock(MollieApiClient::class);
        $apiClient->profiles = $profilesEndPoint;

        $this->apiFactory = $this->createConfiguredMock(MollieApiFactory::class, [
            'createLiveClient' => $apiClient,
            'createTestClient' => $apiClient,
        ]);
    }

    /**
     * @test
     * @testdox Method getConfigArray() does call the shop service getAllShops() method.
     *
     * @return void
     */
    public function methodGetConfigArrayDoesCallShopService()
    {
        $this->shopService
            ->expects(self::once())
            ->method('getAllShops')
            ->willReturn([]);

        $result = $this->configExporter->getConfigArray();

        self::assertEmpty($result);
    }

    /**
     * @test
     * @testdox Method getConfigArray() does return the expected array value.
     *
     * @return void
     */
    public function methodGetConfigArrayDoesReturnExpectedArrayValue()
    {
        $shop = $this->createConfiguredMock(Shop::class, [
            'getId' => 1,
            'getName' => self::TEST_VALUE_SHOP,
        ]);

        $this->shopService
            ->expects(self::once())
            ->method('getAllShops')
            ->willReturn([$shop]);

        $result = $this->configExporter->getConfigArray();

        self::assertSame([1 => $this->getConfigArrayWithTestValues()], $result);
    }

    /**
     * @test
     * @testdox Method getHumanReadableConfig() does call the shop service getAllShops() method.
     *
     * @return void
     */
    public function methodGetHumanReadableConfigDoesCallShopService()
    {
        $this->shopService
            ->expects(self::once())
            ->method('getAllShops')
            ->willReturn([]);

        $result = $this->configExporter->getHumanReadableConfig();

        self::assertEmpty($result);
    }

    /**
     * @test
     * @testdox Method getHumanReadableConfig() does return the expected text value.
     *
     * @return void
     */
    public function methodGetHumanReadableConfigDoesReturnExpectedTextValue()
    {
        $shop = $this->createConfiguredMock(Shop::class, [
            'getId' => 1,
            'getName' => self::TEST_VALUE_SHOP,
        ]);

        $this->shopService
            ->expects(self::once())
            ->method('getAllShops')
            ->willReturn([$shop]);

        $result = $this->configExporter->getHumanReadableConfig();

        self::assertSame($this->getHumanReadableConfigTextWithTestValues(), $result);
    }

    /**
     * @return array
     */
    private function getConfigArrayWithTestValues()
    {
        return [
            'shop' => self::TEST_VALUE_SHOP,
            'liveApiKey' => null,
            'testApiKey' => null,
            'testModeActive' => self::TEST_VALUE_TEST_MODE_ACTIVE,
            'logLevel' => self::TEST_VALUE_LOG_LEVEL,
            'createOrderBeforePayment' => self::TEST_VALUE_CREATE_ORDER_BEFORE_PAYMENT,
            'paymentMethodsType' => self::TEST_VALUE_PAYMENT_METHODS_TYPE,
            'authorizedPaymentStatusId' => self::TEST_VALUE_AUTHORIZED_PAYMENT_STATUS_ID,
            'transactionNumberType' => self::TEST_VALUE_TRANSACTION_NUMBER_TYPE,
            'updateOrderStatus' => self::TEST_VALUE_UPDATE_ORDER_STATUS,
            'ordersShipOnStatus' => self::TEST_VALUE_ORDERS_SHIP_ON_STATUS,
            'cancelFailedOrders' => self::TEST_VALUE_CANCEL_FAILED_ORDERS,
            'reduceStockOnPayment' => self::TEST_VALUE_REDUCE_STOCK_ON_PAYMENT,
            'resetInvoiceAndShipping' => self::TEST_VALUE_RESET_INVOICE_AND_SHIPPING,
            'autoResetStock' => self::TEST_VALUE_AUTO_RESET_STOCK,
            'shippedStatus' => self::TEST_VALUE_SHIPPED_STATUS,
            'molliePaymentMethodLimits' => self::TEST_VALUE_MOLLIE_PAYMENT_METHOD_LIMITS,
            'paymentStatusMailEnabled' => self::TEST_VALUE_PAYMENT_STATUS_MAIL_ENABLED,
            'enableCreditCardComponent' => self::TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT,
            'enableCreditCardComponentStyling' => self::TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT_STYLING,
            'applePayDirectRestrictions' => self::TEST_VALUE_APPLE_PAY_DIRECT_RESTRICTIONS,
            'bankTransferDueDateDays' => self::TEST_VALUE_BANK_TRANSFER_DUE_DATE_DAYS,
        ];
    }

    /**
     * @return string
     */
    private function getHumanReadableConfigTextWithTestValues()
    {
        $config = [
            'Live API-key' => self::TEST_VALUE_LIVE_API_KEY_VALIDATION,
            'Test API-key' => self::TEST_VALUE_LIVE_API_KEY_VALIDATION,
            'Test Mode Enabled' => self::TEST_VALUE_TEST_MODE_ACTIVE ? 'Yes' : 'No',
            'Log Level' => 'INFO',
            'Create Shopware Order Before Payment' => self::TEST_VALUE_CREATE_ORDER_BEFORE_PAYMENT ? 'Yes' : 'No',
            'API Method' => PaymentMethodType::$types[self::TEST_VALUE_PAYMENT_METHODS_TYPE],
            'Status For Authorized Payments' => self::TEST_VALUE_STATUS_NAME,
            'Transaction Number For Orders' => self::TEST_VALUE_TRANSACTION_NUMBER_TYPE,
            'Update Order Status Automatically Enabled' => self::TEST_VALUE_UPDATE_ORDER_STATUS ? 'Yes' : 'No',
            'Automatic Shipping Status' => self::TEST_VALUE_STATUS_NAME,
            'Cancel Failed Orders Enabled' => self::TEST_VALUE_CANCEL_FAILED_ORDERS ? 'Yes' : 'No',
            'Reduce stock after successful payment' => self::TEST_VALUE_REDUCE_STOCK_ON_PAYMENT ? 'Yes' : 'No',
            'Cancellation Of All Amounts Enabled' => self::TEST_VALUE_RESET_INVOICE_AND_SHIPPING ? 'Yes' : 'No',
            'Reset Stock On Failed Payment Enabled' => self::TEST_VALUE_AUTO_RESET_STOCK ? 'Yes' : 'No',
            'Order Status For Shipped Orders' => self::TEST_VALUE_STATUS_NAME,
            'Use Mollie Payment Method Limits Enabled' => self::TEST_VALUE_MOLLIE_PAYMENT_METHOD_LIMITS ? 'Yes' : 'No',
            'Send Payment Status Mail Enabled' => self::TEST_VALUE_PAYMENT_STATUS_MAIL_ENABLED ? 'Yes' : 'No',
            'Creditcard Components Enabled' => self::TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT ? 'Yes' : 'No',
            'Styling For Creditcard Components Enabled' => self::TEST_VALUE_ENABLE_CREDIT_CARD_COMPONENT_STYLING ? 'Yes' : 'No',
            'Apple Pay Display Restrictions' => 'Not set',
            'Bank Transfer Payment Term (Days)' => self::TEST_VALUE_BANK_TRANSFER_DUE_DATE_DAYS,
        ];

        $configText = '';

        foreach ($config as $key => $value) {
            $configText .= sprintf("%s: %s\n", $key, $value);
        }

        return sprintf("[%s]\n%s\n", self::TEST_VALUE_SHOP, $configText);
    }
}
