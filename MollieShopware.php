<?php

namespace MollieShopware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Enlight_Template_Manager;
use Exception;
use Mollie\Api\MollieApiClient;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayDomainFileDownloader;
use MollieShopware\Components\Attributes;
use MollieShopware\Components\Config;
use MollieShopware\Components\Installer\Attributes\AttributesInstaller;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Schema;
use MollieShopware\Components\Services\PaymentMethodService;
use MollieShopware\Components\Services\ShopService;
use MollieShopware\Components\Snippets\SnippetFile;
use MollieShopware\Components\Snippets\SnippetsCleaner;
use MollieShopware\Models\OrderLines;
use MollieShopware\Models\Payment\Configuration;
use MollieShopware\Models\Payment\Repository;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionItem;
use Psr\Log\LoggerInterface;
use Shopware\Components\DependencyInjection\Bridge\Session;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Routing\Context;

require_once __DIR__ . '/vendor/autoload.php';

class MollieShopware extends Plugin
{
    const PLUGIN_VERSION = '2.5.1';

    const PAYMENT_PREFIX = 'mollie_';


    /**
     * Return Shopware events subscribed to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch' => 'requireDependencies',
            'Enlight_Controller_Front_RouteStartup' => ['fixLanguageShopPush', -10],

            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onOrderPostDispatch',

            'Enlight_Controller_Action_PostDispatch_Backend_Payment' => 'onPaymentPostDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Payment' => 'onPaymentPostDispatch',
        ];
    }


    /**
     * Require composer libraries on a new request
     */
    public function requireDependencies()
    {
    }

    /**
     * In engine/Shopware/Plugins/Default/Core/Router/Bootstrap.php
     * the current shop is determined
     *
     * When a POST request is made with the __shop GET variable,
     * this variable isn't used to get the shop,
     * so when an order is created in a language shop,
     * the push always fails because it can't access the session
     *
     * This is done on the Enlight_Controller_Front_RouteStartup event,
     * because this is the first event in de frontcontroller
     * (engine\Library\Enlight\Controller\Front.php)
     * where the Request has been populated.
     *
     * @param \Enlight_Controller_EventArgs $args
     */
    public function fixLanguageShopPush(\Enlight_Controller_EventArgs $args)
    {
        /** @var \Enlight_Controller_Request_Request $request */
        $request = $args->getRequest();

        if ($request->getQuery('__shop')) {
            $request->setPost('__shop', $request->getQuery('__shop'));
        }
    }

    /**
     * Register Mollie controller
     */
    public function registerController()
    {
        return $this->getPath() . '/Controllers/Frontend/Mollie.php';
    }

    /**
     * Inject some backend ext.js extensions for the order module
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onOrderPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var \Enlight_View $view */
        $view = $controller->View();

        /** @var \Enlight_Controller_Request_Request $request */
        $request = $controller->Request();

        $view->addTemplateDir(__DIR__ . '/Resources/views');

        if ($request->getActionName() == 'load') {
            $view->extendsTemplate('backend/mollie_extend_order/controller/list.js');

            $view->extendsTemplate('backend/mollie_extend_order/model/order_history.js');

            $view->extendsTemplate('backend/mollie_extend_order/view/list/list.js');
            $view->extendsTemplate('backend/mollie_extend_order/view/detail/overview.js');
            $view->extendsTemplate('backend/mollie_extend_order/view/detail/order-history.js');

            $view->extendsTemplate('backend/mollie_extend_order_detail/view/detail/position.js');

            # attention
            # THIS IS REQUIRED HERE
            # i have no clue why its not loaded in the payment post dispatch, it's only working here!
            $view->extendsTemplate('backend/mollie_extend_payment/view/payment/form_panel.js');
        }
    }

    /**
     * Inject some backend ext.js extensions for the order module
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPaymentPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        /** @var \Enlight_View $view */
        $view = $controller->View();

        /** @var \Enlight_Controller_Request_Request $request */
        $request = $controller->Request();

        $view->addTemplateDir(__DIR__ . '/Resources/views');

        if ($request->getActionName() == 'load') {
            $view->extendsTemplate('backend/mollie_extend_payment/controller/payment.js');
        }
    }


    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $this->cleanBackendSnippets();

        // Payments are not created at install,
        // because the user hasn't had the ability to put in an API-key at this time
        //
        // Payments are added on activation of the plugin
        // The user should put in an API key between install and activation

        // clear config cache
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);

        // create database tables
        $this->updateDbTables();

        // add extra attributes
        $attributes = $this->createAttributesInstaller();
        $attributes->updateAttributes();

        parent::install($context);
    }


    /**
     * @param UpdateContext $context
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function update(UpdateContext $context)
    {
        $this->cleanBackendSnippets();

        // clear config cache
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);

        // create database tables
        $this->updateDbTables();

        // add extra attributes
        $attributes = $this->createAttributesInstaller();
        $attributes->updateAttributes();

        // set config value for upgraders from version 1.3
        if (substr($context->getPlugin()->getVersion(), 0, strlen('1.3')) == '1.3') {
            $this->writeConfig($context->getPlugin(), 'orders_api_only_where_mandatory', 'no');
        }

        # update our payment config to have valid
        # entries and new configs automatically applied
        /** @var PaymentMethodsInstaller $paymentInstaller */
        $paymentInstaller = $this->getPaymentMethodInstaller($context);
        $paymentInstaller->updatePaymentConfigs();

        parent::update($context);
    }

    /**
     * @param UninstallContext $context
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function uninstall(UninstallContext $context)
    {
        /** @var PaymentMethodsInstaller $paymentInstaller */
        $paymentInstaller = $this->getPaymentMethodInstaller($context);
        $paymentInstaller->uninstallPaymentMethods();

        // remove extra attributes
        $attributes = $this->createAttributesInstaller();
        $attributes->removeAttributes();

        parent::uninstall($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        /** @var PaymentMethodsInstaller $paymentInstaller */
        $paymentInstaller = $this->getPaymentMethodInstaller($context);
        $paymentInstaller->uninstallPaymentMethods();

        parent::deactivate($context);
    }

    /**
     * @param ActivateContext $context
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function activate(ActivateContext $context)
    {
        // clear config cache
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);

        // update db tables
        $this->updateDbTables();

        /** @var PaymentMethodsInstaller $paymentsInstaller */
        $paymentsInstaller = $this->getPaymentMethodInstaller($context);

        # if we install the plugin from scratch, then we
        # want to activate all payment methods
        $paymentsInstaller->installPaymentMethods(true);


        // download apple pay merchant domain verification file of mollie
        $downloader = new ApplePayDomainFileDownloader();
        $downloader->downloadDomainAssociationFile(Shopware()->DocPath());

        // add index to mol_sw_transactions if not exists
        $this->addIndexToTransactions();

        // cleanup old transaction ordermail variables
        $this->cleanOrdermailVariables();

        // clean old unused payment configs
        $this->cleanLegacyPaymentSettings();

        parent::activate($context);
    }

    /**
     * We use the plugin logger in here, because our
     * own logger hasnt been registered in the process
     * of activating the plugin.
     *
     * @return LoggerInterface
     */
    private function getPluginLogger()
    {
        return $this->container->get('pluginlogger');
    }

    /**
     * Update extra database tables
     */
    protected function updateDbTables()
    {
        try {
            $schema = new Schema($this->container->get('models'));
            $schema->update([
                Transaction::class,
                TransactionItem::class,
                OrderLines::class,
                Configuration::class,
            ]);
        } catch (Exception $ex) {
            $this->getPluginLogger()->error(
                'Error when updating database tables',
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }
    }

    /**
     * Remove extra database tables
     */
    protected function removeDBTables()
    {
        try {
            $schema = new Schema($this->container->get('models'));
            $schema->remove(Transaction::class);
            $schema->remove(TransactionItem::class);
            $schema->remove(OrderLines::class);
            $schema->remove(Configuration::class);
        } catch (Exception $ex) {
            $this->getPluginLogger()->error(
                'Error when removing database tables',
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }
    }


    /**
     * Write value to the config
     *
     * @param \Shopware\Models\Plugin\Plugin $plugin
     * @param $key
     * @param $value
     * @throws Exception
     */
    protected function writeConfig(\Shopware\Models\Plugin\Plugin $plugin, $key, $value)
    {
        try {
            /** @var \Shopware\Components\Model\ModelManager $modelManager */
            $modelManager = Shopware()->Container()->get('models');

            /** @var \Shopware\Models\Shop\Shop[] $shops */
            $shops = $modelManager->getRepository(\Shopware\Models\Shop\Shop::class)->findBy([]);

            /** @var Plugin\ConfigWriter $configWriter */
            $configWriter = new Plugin\ConfigWriter(Shopware()->Models());

            foreach ($shops as $shop) {
                $configWriter->saveConfigElement(
                    $plugin,
                    $key,
                    $value,
                    $shop
                );
            }
        } catch (Exception $ex) {
            //
        }
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

        /** @var Plugin\ConfigReader $configReader */
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
     * Returns an instance of the payment method service.
     *
     * @param mixed $context
     * @return PaymentMethodsInstaller
     */
    protected function getPaymentMethodInstaller($context)
    {
        /** @var ModelManager $modelManager */
        $modelManager = $this->container->get('models');

        /** @var Plugin\PaymentInstaller $paymentInstaller */
        $paymentInstaller = $this->container->get('shopware.plugin_payment_installer');

        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = null;

        /** @var Enlight_Template_Manager $templateManager */
        $templateManager = $this->container->get('template');

        if ($modelManager !== null && $paymentInstaller !== null && $templateManager !== null) {
            $config = new Config(
                $this->container->get('shopware.plugin.cached_config_reader'),
                new ShopService(Shopware()->Models())
            );

            $paymentMethodService = new PaymentMethodsInstaller(
                $modelManager,
                $config,
                $paymentInstaller,
                $templateManager,
                $this->getPluginLogger(),
                $context->getPlugin()->getName()
            );
        }

        return $paymentMethodService;
    }

    /**
     *
     */
    private function cleanBackendSnippets()
    {
        $connection = $this->container->get('dbal_connection');

        $iniFiles = [
            new SnippetFile(
                'backend/mollie/general',
                __DIR__ . '/Resources/snippets/backend/mollie/general.ini'
            )
        ];

        $cleaner = new SnippetsCleaner($connection, $iniFiles);

        $cleaner->cleanBackendSnippets();
    }

    private function addIndexToTransactions()
    {
        $connection = $this->container->get('dbal_connection');

        $indexExistsCheck = $connection->executeQuery("SELECT COUNT(1) indexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name='mol_sw_transactions' AND index_name='transaction_id_idx';")->fetch();

        $isExisting = ((int)$indexExistsCheck['indexIsThere'] === 1);

        if (!$isExisting) {
            $connection->executeQuery('ALTER TABLE `mol_sw_transactions` ADD INDEX `transaction_id_idx` (`transaction_id`);');
        }
    }

    private function cleanOrdermailVariables()
    {
        /** @var Connection $connection */
        $connection = $this->container->get('dbal_connection');

        $connection->executeQuery('UPDATE mol_sw_transactions SET ordermail_variables = NULL');
    }

    /**
     *
     */
    private function cleanLegacyPaymentSettings()
    {
        /** @var Repository $repoPaymentConfig */
        $repoPaymentConfig = $this->container->get('models')->getRepository(Configuration::class);

        $repoPaymentConfig->cleanLegacyData();
    }

    /**
     * @return AttributesInstaller
     */
    private function createAttributesInstaller()
    {
        return new AttributesInstaller(
            $this->container->get('models'),
            $this->container->get('shopware_attribute.crud_service')
        );
    }
}
