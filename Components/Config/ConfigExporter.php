<?php

namespace MollieShopware\Components\Config;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Components\Mollie\MollieApiTester;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Services\ShopService;
use Monolog\Logger;
use Shopware\Models\Order\Status;
use Shopware\Models\Shop\Shop;

class ConfigExporter
{
    /**
     * @var Config
     */
    private $config;

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
     * Creates a new instance of the config exporter.
     *
     * @param Config $config
     * @param EntityManagerInterface $entityManager
     * @param MollieApiFactory $apiFactory
     * @param ShopService $shopService
     */
    public function __construct(
        Config $config,
        EntityManagerInterface $entityManager,
        MollieApiFactory $apiFactory,
        ShopService $shopService
    ) {
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->apiFactory = $apiFactory;
        $this->shopService = $shopService;
    }

    /**
     * Returns an array of configuration values for
     * all shops, when humanReadable is true, the
     * keys and values are transformed in a
     * way that is more reader friendly.
     *
     * @param bool $humanReadable
     * @return array<string, array>
     */
    public function getConfigArray($humanReadable = false)
    {
        $config = [];
        $shops = $this->shopService->getAllShops();

        if (empty($shops)) {
            return [];
        }

        foreach ($shops as $shop) {
            $config[$shop->getId()] = $humanReadable
                ? $this->getShopHumanReadableConfigArray($shop)
                : $this->getShopConfigArray($shop);
        }

        return $config;
    }

    /**
     * Returns an array of configuration
     * values for a specific shop.
     *
     * @param Shop $shop
     * @return array<string, mixed>
     */
    public function getShopConfigArray($shop)
    {
        $this->config->setShop($shop->getId());

        return [
            'shop' => $shop->getName(),
            'liveApiKey' => null,
            'testApiKey' => null,
            'testModeActive' => $this->config->isTestmodeActive(),
            'logLevel' => $this->config->getLogLevel(),
            'createOrderBeforePayment' => $this->config->createOrderBeforePayment(),
            'paymentMethodsType' => $this->config->getPaymentMethodsType(),
            'authorizedPaymentStatusId' => $this->config->getAuthorizedPaymentStatusId(),
            'transactionNumberType' => $this->config->getTransactionNumberType(),
            'updateOrderStatus' => $this->config->updateOrderStatus(),
            'ordersShipOnStatus' => $this->config->getOrdersShipOnStatus(),
            'cancelFailedOrders' => $this->config->cancelFailedOrders(),
            'resetInvoiceAndShipping' => $this->config->resetInvoiceAndShipping(),
            'autoResetStock' => $this->config->autoResetStock(),
            'shippedStatus' => $this->config->getShippedStatus(),
            'molliePaymentMethodLimits' => $this->config->useMolliePaymentMethodLimits(),
            'paymentStatusMailEnabled' => $this->config->isPaymentStatusMailEnabled(),
            'enableCreditCardComponent' => $this->config->enableCreditCardComponent(),
            'enableCreditCardComponentStyling' => $this->config->enableCreditCardComponentStyling(),
            'applePayDirectRestrictions' => $this->config->getApplePayDirectRestrictions(),
            'bankTransferDueDateDays' => $this->config->getBankTransferDueDateDays(),
        ];
    }

    /**
     * Returns an array of configuration values for a
     * specific shop that is more humanly readable.
     *
     * @param Shop $shop
     * @return array<string, mixed>
     */
    public function getShopHumanReadableConfigArray($shop)
    {
        $config = $this->validateApiKeys($this->getShopConfigArray($shop), $shop);

        if (isset($config['paymentMethodsType'])) {
            $config['paymentMethodsType'] = $this->getApiMethodName($config['paymentMethodsType']);
        }

        if (isset($config['logLevel'])) {
            $config['logLevel'] = $this->getLogLevelName($config['logLevel']);
        }

        $humanReadableConfig = [];

        foreach ($config as $key => $value) {
            if (is_bool($value)) {
                $config[$key] = $value === true ? 'Yes' : 'No';
            }

            if (is_array($value)) {
                $config[$key] = implode(', ', $value);
            }

            if (stripos($key, 'status') !== false) {
                $config[$key] = $this->getStatusName($value);
            }

            $humanReadableConfig[$this->getHumanReadableLabel($key)] = $config[$key];
        }

        return $humanReadableConfig;
    }

    /**
     * Returns a label for a specific key
     * that is more humanly readable.
     *
     * @param $key
     * @return string
     */
    private function getHumanReadableLabel($key)
    {
        $labels = [
            'shop' => 'Shop',
            'liveApiKey' => 'Live API-key',
            'testApiKey' => 'Test API-key',
            'testModeActive' => 'Test Mode Enabled',
            'logLevel' => 'Log Level',
            'createOrderBeforePayment' => 'Create Shopware Order Before Payment',
            'paymentMethodsType' => 'API Method',
            'authorizedPaymentStatusId' => 'Status For Authorized Payments',
            'transactionNumberType' => 'Transaction Number For Orders',
            'updateOrderStatus' => 'Update Order Status Automatically Enabled',
            'shippedStatus' => 'Order Status For Shipped Orders',
            'cancelFailedOrders' => 'Cancel Failed Orders Enabled',
            'resetInvoiceAndShipping' => 'Cancellation Of All Amounts Enabled',
            'autoResetStock' => 'Reset Stock On Failed Payment Enabled',
            'ordersShipOnStatus' => 'Automatic Shipping Status',
            'molliePaymentMethodLimits' => 'Use Mollie Payment Method Limits Enabled',
            'paymentStatusMailEnabled' => 'Send Payment Status Mail Enabled',
            'enableCreditCardComponent' => 'Creditcard Components Enabled',
            'enableCreditCardComponentStyling' => 'Styling For Creditcard Components Enabled',
            'applePayDirectRestrictions' => 'Apple Pay Display Restrictions',
            'bankTransferDueDateDays' => 'Bank Transfer Payment Term (Days)',
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        return $key;
    }

    /**
     * Returns the name of the API method, based on its key.
     *
     * @param $key
     * @return string
     */
    private function getApiMethodName($key)
    {
        return isset(PaymentMethodType::$types[$key]) ? PaymentMethodType::$types[$key] : '';
    }

    /**
     * Returns the name of a log level, based on its level.
     *
     * @param $level
     * @return string
     */
    private function getLogLevelName($level)
    {
        try {
            $name = Logger::getLevelName($level);
        } catch (Exception $exception) {
        }

        return isset($name) ? $name : '';
    }

    /**
     * Returns the name of a status, based on its id.
     *
     * @param int|null $statusId
     * @return int|string
     */
    private function getStatusName($statusId)
    {
        try {
            /** @var Status|null $status */
            $status = $this->entityManager->getRepository(Status::class)->find($statusId);
        } catch (Exception $exception) {
        }

        return isset($status) ? $status->getName() : $statusId;
    }

    /**
     * Validates the API keys in the configuration
     * and updates the values in the given array.
     *
     * @param array<string, mixed> $config
     * @param Shop $shop
     * @return void
     */
    private function validateApiKeys($config, $shop)
    {
        $apiTester = new MollieApiTester();

        try {
            $liveClient = $this->apiFactory->createLiveClient($shop->getId());
            $isLiveValid = $apiTester->isConnectionValid($liveClient);
        } catch (Exception $exception) {
        }

        try {
            $testClient = $this->apiFactory->createTestClient($shop->getId());
            $isTestValid = $apiTester->isConnectionValid($testClient);
        } catch (Exception $exception) {
        }

        $config['liveApiKey'] = isset($isLiveValid) && $isLiveValid ? 'Valid' : 'Invalid';
        $config['testApiKey'] = isset($isTestValid) && $isTestValid ? 'Valid' : 'Invalid';

        return $config;
    }
}
