<?php

use MollieShopware\Components\Constants\BankTransferFlow;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentMethodType;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use MollieShopware\Exceptions\MolliePaymentConfigurationNotFound;
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
     * @var PaymentMethodsInstaller
     */
    private $paymentInstaller;


    /**
     *
     */
    public function updateAction()
    {
        $this->loadServices();

        $this->logger->info('Starting payment methods import in Backend');

        try {

            /** @var PaymentMethodsInstaller $paymentMethodService */
            $paymentMethodService = $this->container->get('mollie_shopware.payments.installer');

            $importCount = $paymentMethodService->installPaymentMethods(false);

            $this->logger->info($importCount . ' Payment Methods have been successfully imported in Backend');

            $message = sprintf('%d Payment Methods were imported/updated', $importCount);
            echo $message;
            ob_end_flush();
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when importing payment methods in Backend',
                [
                    'error' => $e->getMessage(),
                ]
            );

            http_response_code(500);
            echo $e->getMessage();
            ob_end_flush();
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

            /** @var array $payments */
            $payments = $this->getMolliePaymentMethods($paymentId);

            if (count($payments) <= 0) {
                # we dont have a valid mollie payment
                # just return, that this is no mollie payment
                $this->returnSuccess('', [
                    'isMollie' => false,
                ]);
                return;
            }


            /** @var Payment $payment */
            $payment = $payments[0];


            try {
                $paymentConfig = $this->repoConfiguration->getByPaymentId($payment->getId());
            } catch (MolliePaymentConfigurationNotFound $ex) {

                # if we do not have a config here
                # then make sure to update our payment configs and repair it.
                # afterwards fetch our config again
                $this->paymentInstaller->updatePaymentConfigs();

                $paymentConfig = $this->repoConfiguration->getByPaymentId($payment->getId());
            }


            $paymentMethodType = (int)$paymentConfig->getMethodType();
            $worksWithPaymentsApi = PaymentMethodType::isPaymentsApiAllowed($payment->getName());

            # if its not working with payments API
            # always switch to orders api
            if ($paymentMethodType === PaymentMethodType::PAYMENTS_API && !$worksWithPaymentsApi) {
                $paymentMethodType = PaymentMethodType::ORDERS_API;
            }

            $data = [
                'isMollie' => true,
                'expirationDays' => (string)$paymentConfig->getExpirationDays(),
                'method' => $paymentMethodType,
                'orderCreation' => (int)$paymentConfig->getOrderCreation(),
                'bankTransferFlow' => (int)$paymentConfig->getBankTransferFlow(),
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

            /** @var array $payments */
            $payments = $this->getMolliePaymentMethods($paymentId);

            if (count($payments) <= 0) {
                # if we have not found a mollie payment for this id
                # then don't do anything
                $this->returnSuccess('', []);
                return;
            }

            /** @var Payment $payment */
            $payment = $payments[0];

            $expirationDays = (string)$request->getParam('expirationDays', '');
            $methodType = (int)$request->getParam('methodType', PaymentMethodType::GLOBAL_SETTING);
            $orderCreationType = (int)$request->getParam('orderCreation', OrderCreationType::GLOBAL_SETTING);
            $bankTransferFlow = (int)$request->getParam('bankTransferFlow', BankTransferFlow::NORMAL);


            $paymentConfig = $this->repoConfiguration->getByPaymentId($payment->getId());

            # update with our new settings
            $paymentConfig->setExpirationDays($expirationDays);
            $paymentConfig->setMethodType($methodType);
            $paymentConfig->setOrderCreation($orderCreationType);
            $paymentConfig->setBankTransferFlow($bankTransferFlow);

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

        $this->paymentInstaller = $this->container->get('mollie_shopware.payments.installer');
    }

    /**
     * @param $paymentId
     * @return int|mixed|string
     */
    private function getMolliePaymentMethods($paymentId)
    {
        /** @var \Doctrine\ORM\QueryBuilder $qb */
        $qb = $this->container->get('models')->createQueryBuilder();

        $qb->select('p')
            ->from(Payment::class, 'p')
            ->where($qb->expr()->eq('p.id', ':id'))
            ->andWhere($qb->expr()->like('p.name', ':namePattern'))
            ->setParameter(':namePattern', 'mollie_%')
            ->setParameter(':id', $paymentId);

        return $qb->getQuery()->getResult();
    }
}
