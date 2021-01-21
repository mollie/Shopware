<?php

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayDomainFileDownloader;
use MollieShopware\Components\Config;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Services\PaymentMethodService;
use MollieShopware\Components\Services\ShopService;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Plugin\PaymentInstaller;

class Shopware_Controllers_Backend_MolliePayments extends Shopware_Controllers_Backend_ExtJs
{
    public function updateAction()
    {
        try {
            /** @var PaymentMethodService $paymentMethodService */
            $paymentMethodService = $this->getPaymentMethodService();

            if ($paymentMethodService !== null) {
                // Deactivate all Mollie payment methods
                $paymentMethodService->deactivatePaymentMethods();

                // Get all active payment methods from Mollie
                $methods = $paymentMethodService->getPaymentMethodsFromMollie();
            }

            // Install the payment methods from Mollie
            if ($methods !== null) {
                $paymentMethodService->installPaymentMethod($this->container->getParameter('mollie_shopware.plugin_name'), $methods);
            }

            // download apple pay merchant domain verification file of mollie
            $downloader = new ApplePayDomainFileDownloader();
            $downloader->downloadDomainAssociationFile(Shopware()->DocPath());

            Shopware()->Template()->assign('response', 'Success!');
        } catch (\Throwable $e) {
            $this->logger->addError($e->getMessage());

            $this->response->setStatusCode(500);
            $this->View()->assign('response', $e->getMessage());
        }
    }

    private function getPaymentMethodService()
    {
        /** @var ModelManager $modelManager */
        $modelManager = $this->container->get('models');

        /** @var MollieApiClient $mollieApiClient */
        $mollieApiClient = $this->getMollieApiClient();

        /** @var PaymentInstaller $paymentInstaller */
        $paymentInstaller = $this->container->get('shopware.plugin_payment_installer');

        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = null;

        /** @var Enlight_Template_Manager $templateManager */
        $templateManager = $this->container->get('template');

        // Create an instance of the payment method service
        if (
            $modelManager !== null
            && $mollieApiClient !== null
            && $paymentInstaller !== null
            && $templateManager !== null
        ) {
            $paymentMethodService = new PaymentMethodService(
                $modelManager,
                $mollieApiClient,
                $paymentInstaller,
                $templateManager,
                $this->getPluginLogger()
            );
        }

        return $paymentMethodService;
    }

    /**
     * Returns an instance of the Mollie API client.
     *
     * @return MollieApiClient
     */
    protected function getMollieApiClient()
    {
        /** @var Config $config */
        $config = null;

        /** @var ConfigReader $configReader */
        $configReader = $this->container->get('shopware.plugin.cached_config_reader');

        /** @var ShopService $shopService */
        $shopService = new ShopService($this->container->get('models'));

        /** @var MollieApiFactory $factory */
        $factory = null;

        /** @var MollieApiClient $mollieApiClient */
        $mollieApiClient = null;

        // Get the config
        if ($configReader !== null) {
            $config = new Config($configReader, $shopService);
        }

        // Get the Mollie API factory service
        if ($config !== null) {
            $factory = new MollieApiFactory($config, $this->getPluginLogger());
        }

        // Create the Mollie API client
        if ($factory !== null) {
            try {
                $mollieApiClient = $factory->create();
            } catch (Exception $e) {
                //
            }
        }

        return $mollieApiClient;
    }

    /**
     * @return LoggerInterface
     */
    private function getPluginLogger()
    {
        return $this->container->get('pluginlogger');
    }
}