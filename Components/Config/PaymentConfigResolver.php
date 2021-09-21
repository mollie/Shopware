<?php

namespace MollieShopware\Components\Config;

use MollieShopware\Components\Constants\BankTransferFlow;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use MollieShopware\Components\Translation\Translation;
use MollieShopware\Exceptions\MolliePaymentConfigurationNotFound;
use MollieShopware\Models\Payment\Configuration;
use MollieShopware\Models\Payment\ConfigurationKeys;
use MollieShopware\Models\Payment\Repository;
use MollieShopware\MollieShopware;
use Shopware\Components\Model\ModelManager;

class PaymentConfigResolver
{

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var Repository
     */
    private $repoPaymentConfig;

    /**
     * @var Translation
     */
    private $translations;

    /**
     * @var PaymentMethodsInstaller
     */
    private $paymentInstaller;


    /**
     * @param ConfigFactory $configFactory
     * @param ModelManager $models
     * @param Translation $translations
     * @param PaymentMethodsInstaller $paymentInstaller
     */
    public function __construct(ConfigFactory $configFactory, ModelManager $models, Translation $translations, PaymentMethodsInstaller $paymentInstaller)
    {
        $this->configFactory = $configFactory;
        $this->translations = $translations;
        $this->paymentInstaller = $paymentInstaller;

        /** @var Repository $repoPayments */
        $repoPayments = $models->getRepository(Configuration::class);
        $this->repoPaymentConfig = $repoPayments;
    }


    /**
     * Gets the final method type for the provided
     * payment method in the provided shop.
     * This is either the one from the payment configuration,
     * or the global plugin setting if that has been configured.
     *
     * @param string $paymentMethod
     * @param int $shopId
     * @return int
     * @throws MolliePaymentConfigurationNotFound
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFinalMethodType($paymentMethod, $shopId)
    {
        $fullPaymentMethod = $this->getPaymentFullName($paymentMethod);
        $cleanPaymentMethod = str_replace(MollieShopware::PAYMENT_PREFIX, '', $paymentMethod);


        # fetch the configuration for this payment method
        $pluginConfig = $this->configFactory->getForShop($shopId);
        $paymentConfig = $this->getPaymentConfig($fullPaymentMethod);

        # get any translated values
        # for our provided shop
        $translatedValue = $this->translations->getPaymentConfigTranslation(
            ConfigurationKeys::METHODS_API,
            $paymentConfig->getPaymentMeanId(),
            $shopId
        );

        # use either the snippet or the
        # value from the config directly
        $methodType = (!empty($translatedValue)) ? (int)$translatedValue : (int)$paymentConfig->getMethodType();

        # if we have no real value
        # then make sure to use the global setting
        if ($methodType !== PaymentMethodType::ORDERS_API && $methodType !== PaymentMethodType::PAYMENTS_API) {
            $methodType = PaymentMethodType::GLOBAL_SETTING;
        }

        # if we should use our global setting,
        # then use the one from out plugin configuration
        if ($methodType === PaymentMethodType::GLOBAL_SETTING) {
            $methodType = $pluginConfig->getPaymentMethodsType();
        }

        # make sure to validate it one more time, because some
        # payment methods have strict guides on what to use
        $worksWithPaymentsApi = PaymentMethodType::isPaymentsApiAllowed($cleanPaymentMethod);

        # if payments is not allowed, or orders api is used, then switch to Orders API
        $useOrdersAPI = ($methodType === PaymentMethodType::ORDERS_API || !$worksWithPaymentsApi);


        if ($useOrdersAPI) {
            return PaymentMethodType::ORDERS_API;
        }

        return PaymentMethodType::PAYMENTS_API;
    }

    /**
     * Gets the final order creation option for the provided
     * payment method in the provided shop.
     * This is either the one from the payment configuration,
     * or the global plugin setting if that has been configured.
     *
     * @param string $paymentMethod
     * @param int $shopId
     * @return int
     * @throws MolliePaymentConfigurationNotFound
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFinalOrderCreation($paymentMethod, $shopId)
    {
        $fullPaymentMethod = $this->getPaymentFullName($paymentMethod);

        # fetch the configuration for this payment method
        $pluginConfig = $this->configFactory->getForShop($shopId);
        $paymentConfig = $this->getPaymentConfig($fullPaymentMethod);

        # get any translated values
        # for our provided shop
        $translatedValue = $this->translations->getPaymentConfigTranslation(
            ConfigurationKeys::ORDER_CREATION,
            $paymentConfig->getPaymentMeanId(),
            $shopId
        );

        # use either the snippet or the
        # value from the config directly
        $orderCreation = (!empty($translatedValue)) ? (int)$translatedValue : (int)$paymentConfig->getOrderCreation();

        # if we somehow have a weird value that is neither one of our options,
        # then make sure we use the global setting
        if ($orderCreation !== OrderCreationType::BEFORE_PAYMENT && $orderCreation !== OrderCreationType::AFTER_PAYMENT) {
            $orderCreation = OrderCreationType::GLOBAL_SETTING;
        }

        # if we should use our global setting,
        # then use the one from out plugin configuration
        if ($orderCreation === OrderCreationType::GLOBAL_SETTING) {
            $orderCreation = ($pluginConfig->createOrderBeforePayment()) ? OrderCreationType::BEFORE_PAYMENT : OrderCreationType::AFTER_PAYMENT;
        }

        return $orderCreation;
    }

    /**
     * Gets the final order creation option for the provided
     * payment method in the provided shop.
     * This is either the one from the payment configuration,
     * or the global plugin setting if that has been configured.
     *
     * @param string $paymentMethod
     * @param int $shopId
     * @return string
     * @throws MolliePaymentConfigurationNotFound
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFinalOrderExpiration($paymentMethod, $shopId)
    {
        $fullPaymentMethod = $this->getPaymentFullName($paymentMethod);

        # fetch the configuration for this payment method
        $paymentConfig = $this->getPaymentConfig($fullPaymentMethod);

        # get any translated values
        # for our provided shop
        $translatedValue = $this->translations->getPaymentConfigTranslation(
            ConfigurationKeys::EXPIRATION_DAYS,
            $paymentConfig->getPaymentMeanId(),
            $shopId
        );

        # use either the snippet or the
        # value from the config directly
        $expirationDays = (!empty($translatedValue)) ? $translatedValue : $paymentConfig->getExpirationDays();

        return (string)$expirationDays;
    }

    /**
     * @param string $paymentMethod
     * @param int $shopId
     * @return bool
     * @throws MolliePaymentConfigurationNotFound
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function getFinalIsEasyBankTransfer($paymentMethod, $shopId)
    {
        $fullPaymentMethod = $this->getPaymentFullName($paymentMethod);

        # fetch the configuration for this payment method
        $paymentConfig = $this->getPaymentConfig($fullPaymentMethod);

        # get any translated values for our provided shop
        $translatedValue = $this->translations->getPaymentConfigTranslation(
            ConfigurationKeys::BANKTRANSFER_FLOW,
            $paymentConfig->getPaymentMeanId(),
            $shopId
        );

        $flowType = (int)$paymentConfig->getBankTransferFlow();

        if (!empty($translatedValue)) {
            $flowType = (int)$translatedValue;
        }

        if ($flowType === BankTransferFlow::EASY) {
            return true;
        }

        return false;
    }

    /**
     * @param string $paymentName
     * @return string
     */
    private function getPaymentFullName($paymentName)
    {
        if (strpos($paymentName, MollieShopware::PAYMENT_PREFIX) === 0) {
            return $paymentName;
        }

        return MollieShopware::PAYMENT_PREFIX . $paymentName;
    }

    /**
     * @param string $fullPaymentMethod
     * @return Configuration
     * @throws MolliePaymentConfigurationNotFound
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function getPaymentConfig($fullPaymentMethod)
    {
        try {
            return $this->repoPaymentConfig->getByPaymentName($fullPaymentMethod);
        } catch (MolliePaymentConfigurationNotFound $exception) {
            $this->paymentInstaller->updatePaymentConfigs();
        }

        return $this->repoPaymentConfig->getByPaymentName($fullPaymentMethod);
    }

}
