<?php

namespace MollieShopware\Components\Services;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Enlight_Template_Manager;
use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\BaseCollection;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\Api\Types\PaymentMethod;
use MollieShopware\Components\ApplePayDirect\ApplePayDirectHandlerInterface;
use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use MollieShopware\Components\Helpers\LogHelper;
use Shopware\Components\Api\Resource\PaymentMethods;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\PaymentInstaller;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Payment\Repository as PaymentRepository;

class PaymentMethodService
{
    const PAYMENT_METHOD_ACTION = 'action';
    const PAYMENT_METHOD_ACTIVE = 'active';
    const PAYMENT_METHOD_ADDITIONAL_DESCRIPTION = 'additionalDescription';
    const PAYMENT_METHOD_DESCRIPTION = 'description';
    const PAYMENT_METHOD_COUNTRIES = 'countries';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_POSITION = 'position';
    const PAYMENT_METHOD_SURCHARGE = 'surcharge';
    const PAYMENT_METHOD_TEMPLATE_DIR = __DIR__ . '/../../Resources/views/frontend/plugins/payment';

    /** @var ModelManager */
    private $modelManager;

    /** @var MollieApiClient */
    private $mollieApiClient;

    /** @var PaymentInstaller */
    private $paymentInstaller;

    /** @var Enlight_Template_Manager */
    private $templateManager;

    /**
     * Creates a new instance of the payment method service.
     *
     * @param ModelManager $modelManager
     * @param MollieApiClient $mollieApiClient
     * @param PaymentInstaller $paymentInstaller
     * @param Enlight_Template_Manager $templateManager
     */
    public function __construct(
        ModelManager $modelManager,
        MollieApiClient $mollieApiClient,
        PaymentInstaller $paymentInstaller,
        Enlight_Template_Manager $templateManager
    ) {
        $this->modelManager = $modelManager;
        $this->mollieApiClient = $mollieApiClient;
        $this->paymentInstaller = $paymentInstaller;
        $this->templateManager = $templateManager;
    }

    /**
     * Returns a payment method by an array of params, or returns null
     * if no payment method is found.
     *
     * @param array $params
     * @return object|Payment|null
     */
    public function getPaymentMethod(array $params)
    {
        /** @var null|Payment $paymentMethod */
        $paymentMethod = null;

        /** @var PaymentRepository $paymentMethodRepository */
        $paymentRepository = $this->modelManager
            ->getRepository(Payment::class);

        // Find the payment method by the given params
        if ($paymentRepository !== null) {
            $paymentMethod = $paymentRepository->findOneBy($params);
        }

        return $paymentMethod;
    }

    /**
     * Returns an array of payment methods from the Mollie API.
     *
     * @return array
     * @todo Get methods in the correct locale (de_DE en_US es_ES fr_FR nl_BE fr_BE nl_NL)
     *
     */
    public function getPaymentMethodsFromMollie()
    {
        // Variables
        $options = [];
        $position = 0;

        /** @var null|MethodCollection $methods */
        $methods = $this->getActivePaymentMethodsFromMollie();

        if ($methods !== null) {
            # if its not null, do the same again
            # please note we give the original list into it
            # to avoid duplicate adding (without changing anything else)
            $methods = $this->appendApplePayDirectFeature($this->getActivePaymentMethodsFromMollie());
        }


        // Add the template directory to the template manager
        $this->templateManager->addTemplateDir(__DIR__ . '/Resources/views');

        // Assign the Shopware router to the template manager
        if (Shopware()->Front() !== null) {
            $this->templateManager->assign('router', Shopware()->Front()->Router());
        }

        // Add options to the array
        foreach ($methods as $method) {
            $option = $this->getPreparedPaymentOption($method, $position);

            if (is_array($option)) {
                $options[] = $option;
                $position++;
            }
        }

        return $options;
    }

    /**
     * Installs an array of payment methods.
     *
     * @param string $pluginName
     * @param array $methods
     */
    public function installPaymentMethod($pluginName, array $methods)
    {
        foreach ($methods as $method) {
            /** @var Payment $existingMethod */
            $existingMethod = null;

            // Retrieve existing information so it doesn't get overwritten
            if (isset($method[self::PAYMENT_METHOD_NAME], $method[self::PAYMENT_METHOD_ACTION])) {
                $existingMethod = $this->getPaymentMethod(
                    [
                        self::PAYMENT_METHOD_NAME => $method[self::PAYMENT_METHOD_NAME],
                    ]
                );
            }

            if ($existingMethod === null) {
                // setup for new payment methods
                // -----------------------------------------------------
                // new payment methods are all activated
                // but not apple pay direct, that wouldn't be good in the storefront ;)
                if ($method[self::PAYMENT_METHOD_NAME] === ShopwarePaymentMethod::APPLEPAYDIRECT) {
                    $method[self::PAYMENT_METHOD_ACTIVE] = 0;
                }
            } else {
                // Set existing data on method
                $method[self::PAYMENT_METHOD_ADDITIONAL_DESCRIPTION] = (string)$existingMethod->getAdditionalDescription();
                $method[self::PAYMENT_METHOD_COUNTRIES] = $existingMethod->getCountries();
                $method[self::PAYMENT_METHOD_DESCRIPTION] = (string)$existingMethod->getDescription();
                $method[self::PAYMENT_METHOD_POSITION] = $existingMethod->getPosition();
                $method[self::PAYMENT_METHOD_SURCHARGE] = $existingMethod->getSurcharge();
            }

            // Install the payment method in Shopware
            $this->paymentInstaller->createOrUpdate($pluginName, $method);
        }
    }

    /**
     * Deactivates all payment methods created by the Mollie plugin.
     */
    public function deactivatePaymentMethods()
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->modelManager->createQueryBuilder();

        /** @var Query $query */
        $query = $queryBuilder->update(Payment::class, 'paymentMethod')
            ->set('paymentMethod.active', '?1')
            ->where($queryBuilder->expr()->like('paymentMethod.name', '?2'))
            ->setParameter(1, false)
            ->setParameter(2, 'mollie_%')
            ->getQuery();

        // Execute the query
        $query->execute();
    }

    /**
     * Returns a collection of active payment methods from the Mollie API.
     *
     * @return null|BaseCollection|MethodCollection
     */
    public function getActivePaymentMethodsFromMollie()
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
            LogHelper::logMessage($e->getMessage(), LogHelper::LOG_ERROR, $e);
        }

        return $methods;
    }

    /**
     * Verify if "Apple Pay" payment method exists and
     * append "Apple Pay Direct" feature
     *
     * @param MethodCollection $methods
     * @return MethodCollection
     */
    private function appendApplePayDirectFeature(MethodCollection $methods)
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
                $methods->append($applePayDirect($method));
                break;
            }
        }

        return $methods;
    }

    /**
     * Returns an option array of a payment method.
     *
     * @param $method
     * @param $position
     * @return array
     */
    private function getPreparedPaymentOption($method, $position = 0)
    {
        // Get the name of the payment method
        $paymentMethodName = 'mollie_' . strtolower($method->id);

        // Get the additional description of a payment method
        $additionalDescription = $this->getAdditionalDescription($method, $paymentMethodName);

        // Build the option array of the payment method
        $option = [
            self::PAYMENT_METHOD_ACTION => 'frontend/Mollie',
            self::PAYMENT_METHOD_ACTIVE => 1,
            self::PAYMENT_METHOD_NAME => $paymentMethodName,
            self::PAYMENT_METHOD_ADDITIONAL_DESCRIPTION => $additionalDescription,
            self::PAYMENT_METHOD_DESCRIPTION => (string)$method->description,
            self::PAYMENT_METHOD_POSITION => $position,
        ];

        // Add the template to the payment method
        if (file_exists(self::PAYMENT_METHOD_TEMPLATE_DIR . '/' . $paymentMethodName . '.tpl')) {
            $option['template'] = $paymentMethodName . '.tpl';
        }

        return $option;
    }

    /**
     * Returns the additional description of a payment method.
     *
     * @param $method
     * @param $paymentMethodName
     * @return string
     */
    private function getAdditionalDescription($method, $paymentMethodName)
    {
        /** @var null|string $additionalDescription */
        $additionalDescription = null;

        // Assign the method to the template manager
        $this->templateManager->assign('method', $method);

        // Get the path of the template file
        $templatePath = self::PAYMENT_METHOD_TEMPLATE_DIR . '/methods/' . $paymentMethodName . '.tpl';

        // If the template doesn't exist, fallback to the default template
        if (!file_exists($templatePath)) {
            $templatePath = self::PAYMENT_METHOD_TEMPLATE_DIR . '/methods/main.tpl';
        }

        // Fetch the additional description from the template
        try {
            $additionalDescription = $this->templateManager->fetch('file:' . $templatePath);
        } catch (Exception $e) {
            // No need to handle this exception, the additional description is simply left null
        }

        return (string)$additionalDescription;
    }
}