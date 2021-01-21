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
            $paymentMethodService = $this->container->get('mollie_shopware.payment_method_service');

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
            $this->getPluginLogger()->addError($e->getMessage());

            $this->response->setStatusCode(500);
            $this->View()->assign('response', $e->getMessage());
        }
    }

    /**
     * @return LoggerInterface
     */
    private function getPluginLogger()
    {
        return $this->container->get('pluginlogger');
    }
}