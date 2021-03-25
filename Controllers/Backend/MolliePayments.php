<?php

use MollieShopware\Components\Services\PaymentMethodService;
use Psr\Log\LoggerInterface;

class Shopware_Controllers_Backend_MolliePayments extends Shopware_Controllers_Backend_ExtJs
{

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     *
     */
    public function updateAction()
    {
        $this->loadServices();

        $this->logger->info('Starting payment methods import in Backend');

        try {

            /** @var PaymentMethodService $paymentMethodService */
            $paymentMethodService = $this->container->get('mollie_shopware.payment_method_service');

            $importCount = $paymentMethodService->installPaymentMethods(false);

            $this->logger->info($importCount . ' Payment Methods have been successfully imported in Backend');

            $message = sprintf('%d Payment Methods were imported/updated', $importCount);

            die($message);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when importing payment methods in Backend',
                [
                    'error' => $e->getMessage(),
                ]
            );

            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     *
     */
    private function loadServices()
    {
        $this->logger = $this->container->get('mollie_shopware.components.logger');
    }
}
