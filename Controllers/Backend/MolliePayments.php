<?php

use MollieShopware\Components\Services\PaymentMethodService;
use MollieShopware\Models\Payment\Configuration;
use MollieShopware\Models\Payment\Repository;
use MollieShopware\Traits\Controllers\BackendControllerTrait;
use Psr\Log\LoggerInterface;
use Shopware\Models\Payment\Payment;

class Shopware_Controllers_Backend_MolliePayments extends Shopware_Controllers_Backend_Application
{

    use BackendControllerTrait;

    /**
     * @var string
     */
    protected $model = Payment::class;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Repository
     */
    private $repoConfiguration;


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
    public function getMollieConfigAction()
    {
        $this->loadServices();

        try {

            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            $paymentId = (int)$request->getParam('paymentId', 0);

            $paymentConfig = $this->repoConfiguration->getByPaymentId($paymentId);


            # if we do not have a config yet
            # make sure to create one in the database
            if (!$paymentConfig instanceof Configuration) {

                $paymentConfig = new Configuration();
                $paymentConfig->setPaymentMeanId($paymentId);

                $this->repoConfiguration->save($paymentConfig);
            }

            $data = [
                'expirationDays' => (string)$paymentConfig->getExpirationDays(),
            ];

            $this->returnSuccess('', $data);

        } catch (Exception $ex) {

            $this->returnError($ex->getMessage());
        }
    }

    /**
     *
     */
    public function saveMollieConfigAction()
    {
        $this->loadServices();

        try {

            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            $paymentId = (int)$request->getParam('paymentId', 0);
            $expirationDays = (string)$request->getParam('expirationDays', '');


            $paymentConfig = $this->repoConfiguration->getByPaymentId($paymentId);

            # if we dont have a config yet
            # then create a new object and configure it
            if (!$paymentConfig instanceof Configuration) {

                $paymentConfig = new Configuration();
                $paymentConfig->setPaymentMeanId($paymentId);
            }

            # update with our new settings
            $paymentConfig->setExpirationDays($expirationDays);

            # save the data
            $this->repoConfiguration->save($paymentConfig);

            $this->returnSuccess('', []);

        } catch (Exception $ex) {

            $this->returnError($ex->getMessage());
        }
    }

    /**
     *
     */
    private function loadServices()
    {
        $this->logger = $this->container->get('mollie_shopware.components.logger');

        $this->repoConfiguration = $this->container->get('models')->getRepository(Configuration::class);
    }

}
