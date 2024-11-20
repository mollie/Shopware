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
     * Returns an array of configuration
     * values for all shops.
     *
     * @return array<int, array<string, array>>
     */
    public function getConfigArray()
    {
        $config = [];
        $shops = $this->shopService->getAllShops();

        if (empty($shops)) {
            return [];
        }

        foreach ($shops as $shop) {
            $config[$shop->getId()] = $this->getShopConfigArray($shop);
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
     * Returns a humanly readable text with
     * the configuration of all subshops.
     *
     * @return string
     */
    public function getHumanReadableConfig()
    {
        $humanReadableConfig = '';
        $shops = $this->shopService->getAllShops();

        if (empty($shops)) {
            return '';
        }

        foreach ($shops as $shop) {
            $humanReadableConfig .= sprintf("%s\n\n", $this->getHumanReadableShopConfig($shop));
        }

        return $humanReadableConfig;
    }

    /**
     * Returns a humanly readable text containing the
     * configuration values for a specific shop.
     *
     * @param Shop $shop
     * @return string
     */
    public function getHumanReadableShopConfig($shop)
    {
        $config = $this->validateApiKeys($this->getShopConfigArray($shop), $shop);

        if (isset($config['paymentMethodsType'])) {
            $config['paymentMethodsType'] = $this->getApiMethodName($config['paymentMethodsType']);
        }

        if (isset($config['logLevel'])) {
            $config['logLevel'] = $this->getLogLevelName($config['logLevel']);
        }

        $humanReadableConfig = isset($config['shop'])
            ? sprintf("[%s]", $config['shop'])
            : '';

        foreach ($config as $key => $value) {
            if ($key === 'shop') {
                continue;
            }

            if (is_null($value) || $value === '') {
                $config[$key] = 'Not set';
            }

            if (is_array($value)) {
                $config[$key] = !empty($value) ? implode(', ', $value) : 'Not set';
            }

            if (is_bool($value)) {
                $config[$key] = $value === true ? 'Yes' : 'No';
            }

            if (stripos($key, 'status') !== false && is_numeric($value)) {
                $config[$key] = $this->getStatusName($value);
            }

            $humanReadableConfig = sprintf(
                "%s\n%s: %s",
                $humanReadableConfig,
                $this->getHumanReadableLabel($key),
                $config[$key]
            );
        }

        return $humanReadableConfig;
    }

    /**
     * Returns a label for a specific key
     * that is more humanly readable.
     *
     * @param string $key
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
     * @param string $key
     * @return string
     */
    private function getApiMethodName($key)
    {
        return isset(PaymentMethodType::$types[$key]) ? PaymentMethodType::$types[$key] : '';
    }

    /**
     * Returns the name of a log level, based on its level.
     *
     * @param int $level
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
     * @param mixed $statusId
     * @return mixed
     */
    private function getStatusName($statusId)
    {
        if (!is_numeric($statusId)) {
            return $statusId;
        }

        try {
            /** @var null|Status $status */
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
     * @return array<string, mixed>
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
