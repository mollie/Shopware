<?php

namespace MollieShopware\Components\Services;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Enlight_Template_Manager;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\BaseCollection;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\Api\Types\PaymentMethod;
use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use MollieShopware\Components\Installer\PaymentMethods\IconHtmlBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\PaymentInstaller;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Payment\Repository as PaymentRepository;


class PaymentMethodService
{

    const PAYMENT_METHOD_TEMPLATE_DIR = __DIR__ . '/../../Resources/views/frontend/plugins/payment';

    const MOLLIE_ACTION_KEY = 'frontend/Mollie';

    /**
     * @var string
     */
    private $pluginName;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var MollieApiClient
     */
    private $mollieApiClient;

    /**
     * @var PaymentInstaller
     */
    private $paymentInstaller;

    /**
     * @var Enlight_Template_Manager
     */
    private $templateManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ModelManager $modelManager
     * @param MollieApiClient $mollieApiClient
     * @param PaymentInstaller $paymentInstaller
     * @param Enlight_Template_Manager $templateManager
     * @param LoggerInterface $logger
     * @param $pluginName
     */
    public function __construct(ModelManager $modelManager, MollieApiClient $mollieApiClient, PaymentInstaller $paymentInstaller, Enlight_Template_Manager $templateManager, LoggerInterface $logger, $pluginName)
    {
        $this->modelManager = $modelManager;
        $this->mollieApiClient = $mollieApiClient;
        $this->paymentInstaller = $paymentInstaller;
        $this->templateManager = $templateManager;
        $this->logger = $logger;
        $this->pluginName = $pluginName;
    }


    /**
     * This function completely installs everything and makes sure
     * that new methods are added and old ones are updated
     *
     * @param bool $forceActivate enables all payments methods except removed ones
     * @return int total number of installed/updated payment methods
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function installPaymentMethods($forceActivate)
    {
        # first get all available payment methods
        # for the connected mollie account
        $availableMolliePayments = $this->getAvailableMolliePayments();

        # now load all installed mollie payment methods
        # that exist in Shopware already
        $installedMethods = $this->getInstalledMolliePayments();

        # now its time to load the delta,
        # what payment methods have to be inserted, what are updated
        # and methods do not exist anymore in the mollie profile and thus
        # need to be deactivated in Shopware
        $newMethods = $this->getDeltaToInsert($installedMethods, $availableMolliePayments);
        $updateMethods = $this->getDeltaToUpdate($installedMethods, $availableMolliePayments);
        $removedMethods = $this->getDeltaToRemove($installedMethods, $newMethods, $updateMethods);


        # now insert new payment methods
        foreach ($newMethods as $method) {
            $this->logger->info('Installing new payment method: ' . $method['name']);
            $this->addNewMethod($method);
        }

        # update existing ones
        /** @var array $method */
        foreach ($updateMethods as $method) {

            $foundInstalledMethod = null;

            /** @var Payment $installedMethod */
            foreach ($installedMethods as $installedMethod) {
                if ($installedMethod->getName() === $method['name']) {
                    $foundInstalledMethod = $installedMethod;
                    break;
                }
            }

            if ($foundInstalledMethod === null) {
                continue;
            }

            # just use DEBUG for simple updates
            # its clear that they get updated
            $this->logger->debug('Updating payment method: ' . $method['name']);

            $this->updateExistingMethod($method, $foundInstalledMethod);
        }

        # and deactivate the ones that should be removed
        /** @var Payment $method */
        foreach ($removedMethods as $method) {
            $this->logger->info('Deactivating payment method: ' . $method->getName());
            $this->setPaymentActive($method, false);
        }

        # --------------------------------------------------------------------------------
        # if we have forced to activate payment methods (fresh installation)
        # then make sure to activate all, but no method that already exists and
        # is not coming from the mollie payments api anymore (deprecated ones)

        if ($forceActivate) {

            $existingMethods = $this->getInstalledMolliePayments();

            /** @var Payment $method */
            foreach ($existingMethods as $method) {

                # if the method is in the list of removed ones,
                # then dont try to activate it (deprecated method)
                if ($this->isPaymentInList($removedMethods, $method)) {
                    continue;
                }

                # verify if its allowed to activate the
                # current payment method by default
                if ($this->isMethodDefaultActivated($method->getName())) {
                    $this->setPaymentActive($method, true);
                }
            }
        }


        return count($availableMolliePayments);
    }

    /**
     * Makes sure to do everything that is necessary
     * when uninstalling the plugin and deactivating the payment methods.
     */
    public function uninstallPaymentMethods()
    {
        // Don't remove payment methods but set them to inactive.
        // So orders paid still reference an existing payment method
        $methods = $this->getInstalledMolliePayments();

        /** @var Payment $method */
        foreach ($methods as $method) {
            $this->setPaymentActive($method, false);
        }
    }

    /**
     * Gets the apple pay direct payment method
     * if existing and active
     *
     * @return object|Payment|null
     */
    public function getActiveApplePayDirectMethod()
    {
        /** @var PaymentRepository $paymentMethodRepository */
        $paymentRepository = $this->modelManager->getRepository(Payment::class);

        return $paymentRepository->findOneBy([
            'name' => ShopwarePaymentMethod::APPLEPAYDIRECT,
            'active' => true,
        ]);
    }


    /**
     * @return array
     */
    private function getAvailableMolliePayments()
    {
        /** @var MethodCollection $methods */
        $methods = $this->getActivePaymentMethodsFromMollie();

        if ($methods !== null) {
            $methods = $methods->getArrayCopy();
        } else {
            $methods = [];
        }

        # if its not null, do the same again
        # please note we give the original list into it
        # to avoid duplicate adding (without changing anything else)
        $methods = $this->appendApplePayDirectFeature($methods);

        // Add the template directory to the template manager
        $this->templateManager->addTemplateDir(__DIR__ . '/Resources/views');

        // Assign the Shopware router to the template manager
        if (Shopware()->Front() !== null) {
            $this->templateManager->assign('router', Shopware()->Front()->Router());
        }

        $options = [];
        $position = 0;

        $iconBuilder = new IconHtmlBuilder();

        /** @var Method $method */
        foreach ($methods as $method) {

            $paymentMethodName = 'mollie_' . strtolower($method->id);

            $newData = [
                'action' => self::MOLLIE_ACTION_KEY,
                'name' => $paymentMethodName,
                'description' => (string)$method->description,
                'additionalDescription' => $iconBuilder->getIconHTML($method),
                'active' => 1,
                'position' => $position,
                'countries' => [],
                'surcharge' => 0
            ];

            if (file_exists(self::PAYMENT_METHOD_TEMPLATE_DIR . '/' . $paymentMethodName . '.tpl')) {
                $newData['template'] = $paymentMethodName . '.tpl';
            }

            $options[] = $newData;
            $position++;
        }

        return $options;
    }

    /**
     * @param array $method
     */
    private function addNewMethod(array $method)
    {
        # verify if the new method might be black listed
        # and must not be activated by default on installation
        if (!$this->isMethodDefaultActivated($method['name'])) {
            $method['active'] = 0;
        }

        # always make sure our action is correctly set
        # otherwise the payments won't even start and a finish page is visible directly
        $method['action'] = self::MOLLIE_ACTION_KEY;
        
        $this->paymentInstaller->createOrUpdate($this->pluginName, $method);
    }

    /**
     * @param array $method
     * @param Payment $existingMethod
     */
    private function updateExistingMethod(array $method, Payment $existingMethod)
    {
        # reassign all data here that should be used from the existing method.
        # This means, everything that the user is allowed to overwrite should be set in here
        $method['description'] = (string)$existingMethod->getDescription();
        $method['additionalDescription'] = (string)$existingMethod->getAdditionalDescription();

        $method['position'] = $existingMethod->getPosition();

        $method['countries'] = $existingMethod->getCountries();

        $method['surcharge'] = $existingMethod->getSurcharge();

        $method['active'] = $existingMethod->getActive();

        # always make sure our action is correctly set
        # otherwise the payments won't even start and a finish page is visible directly
        $method['action'] = self::MOLLIE_ACTION_KEY;

        $this->paymentInstaller->createOrUpdate($this->pluginName, $method);
    }

    /**
     * @return array
     */
    private function getInstalledMolliePayments()
    {
        $qb = $this->modelManager->createQueryBuilder();

        $qb->select(['p'])
            ->from(Payment::class, 'p')
            ->where($qb->expr()->like('p.name', ':namePattern'))
            ->setParameter(':namePattern', 'mollie_%');

        return $qb->getQuery()->getResult();
    }

    /**
     * Verify if "Apple Pay" payment method exists and
     * append "Apple Pay Direct" feature
     *
     * @param array $methods
     * @return array
     */
    private function appendApplePayDirectFeature(array $methods)
    {
        $applePayDirect = static function ($method) {
            $applePayDirect = clone $method;
            $applePayDirect->id = PaymentMethod::APPLEPAY_DIRECT;
            $applePayDirect->description = 'Apple Pay Direct';
            return $applePayDirect;
        };

        /** @var Method $method */
        foreach ($methods as $method) {
            if ($method->id === PaymentMethod::APPLEPAY) {
                $methods[] = $applePayDirect($method);
                break;
            }
        }

        return $methods;
    }

    /**
     * @param Payment[] $installedMethods
     * @param array $mollieMethods
     * @return array
     */
    private function getDeltaToInsert($installedMethods, $mollieMethods)
    {
        $list = array();

        /** @var array $method */
        foreach ($mollieMethods as $method) {

            /** @var Payment $existingMethod */
            $existingMethod = null;

            /** @var Payment $installed */
            foreach ($installedMethods as $installed) {
                if ($installed->getName() === $method['name']) {
                    $existingMethod = $installed;
                    break;
                }
            }

            if ($existingMethod === null) {
                $list[] = $method;
            }
        }

        return $list;
    }

    /**
     * @param Payment[] $installedMethods
     * @param array $mollieMethods
     * @return array
     */
    private function getDeltaToUpdate($installedMethods, $mollieMethods)
    {
        $list = array();

        /** @var array $method */
        foreach ($mollieMethods as $method) {

            /** @var Payment $existingMethod */
            $existingMethod = null;

            /** @var Payment $installed */
            foreach ($installedMethods as $installed) {
                if ($installed->getName() === $method['name']) {
                    $existingMethod = $installed;
                    break;
                }
            }

            if ($existingMethod !== null) {
                $list[] = $method;
            }
        }

        return $list;
    }

    /**
     * @param $installedMethods
     * @param $newMethods
     * @param $updateMethods
     * @return array
     */
    private function getDeltaToRemove($installedMethods, $newMethods, $updateMethods)
    {
        $existingMethods = array_merge($newMethods, $updateMethods);

        $list = array();

        /** @var Payment $installed */
        foreach ($installedMethods as $installed) {

            $found = false;
            /** @var array $method */
            foreach ($existingMethods as $method) {
                if ($installed->getName() === $method['name']) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $list[] = $installed;
            }
        }

        return $list;
    }

    /**
     * @param $methodName
     * @return bool
     */
    private function isMethodDefaultActivated($methodName)
    {
        // new payment methods are all activated
        // but not apple pay direct, that wouldn't be good in the storefront ;)
        if ($methodName === ShopwarePaymentMethod::APPLEPAYDIRECT) {
            return false;
        }

        return true;
    }

    /**
     * @param Payment $payment
     * @param $isActive
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function setPaymentActive(Payment $payment, $isActive)
    {
        $payment->setActive($isActive);

        $this->modelManager->flush($payment);
    }

    /**
     * Returns a collection of active payment methods from the Mollie API.
     *
     * @return null|BaseCollection|MethodCollection
     */
    private function getActivePaymentMethodsFromMollie()
    {
        /** @var MethodCollection $methods */
        $methods = null;

        try {
            $methods = $this->mollieApiClient->methods->allActive(
                [
                    'resource' => 'orders',
                    'includeWallets' => 'applepay',
                ]
            );
        } catch (ApiException $e) {
            $this->logger->error(
                'Error when loading active payment methods from Mollie',
                array(
                    'error' => $e->getMessage(),
                )
            );
        }

        return $methods;
    }

    /**
     * @param $methods
     * @param Payment $searchedMethod
     * @return bool
     */
    private function isPaymentInList($methods, Payment $searchedMethod)
    {
        /** @var Payment $remove */
        foreach ($methods as $method) {

            if ($method->getName() === $searchedMethod->getName()) {
                return true;
            }
        }

        return false;
    }

}