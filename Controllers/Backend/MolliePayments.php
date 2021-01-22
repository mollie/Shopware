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
        $this->getLogger()->info('Importing payment methods.');
        try {
            /** @var PaymentMethodService $paymentMethodService */
            $paymentMethodService = $this->container->get('mollie_shopware.payment_method_service');

            // Deactivate all Mollie payment methods
            $paymentMethodService->deactivatePaymentMethods();

            // Get all active payment methods from Mollie
            $methods = $paymentMethodService->getPaymentMethodsFromMollie();

            // Install the payment methods from Mollie
            $paymentMethodService->installPaymentMethod($this->container->getParameter('mollie_shopware.plugin_name'), $methods);

            Shopware()->Template()->assign(
                'response',
                sprintf('%d Payment Methods were imported/ updated', count($methods))
            );
        } catch (\Throwable $e) {
            $this->getLogger()->error($e->getMessage());

            $this->response->setStatusCode(500);
            $this->View()->assign('response', $e->getMessage());
        }
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger()
    {
        return $this->container->get('mollie_shopware.components.logger');
    }
}