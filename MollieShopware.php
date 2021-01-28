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
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Schema;
use MollieShopware\Components\Services\PaymentMethodService;
use MollieShopware\Components\Services\ShopService;
use MollieShopware\Components\Snippets\SnippetFile;
use MollieShopware\Components\Snippets\SnippetsCleaner;
use MollieShopware\Models\OrderLines;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionItem;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;

class MollieShopware extends Plugin
{


    const PLUGIN_VERSION = '1.7.0';

    const PAYMENT_PREFIX = 'mollie_';


    /**
     * Return Shopware events subscribed to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch' => 'requireDependencies',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onOrderPostDispatch',
            'Enlight_Controller_Front_RouteStartup' => ['fixLanguageShopPush', -10],
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
        ];
    }

    /**
     * Require composer libraries on a new request
     */
    public function requireDependencies()
    {
        // Load composer libraries
        if (file_exists($this->getPath() . '/Client/vendor/scoper-autoload.php')) {
            require_once $this->getPath() . '/Client/vendor/scoper-autoload.php';
        }

        // Load guzzle functions
        if (file_exists($this->getPath() . '/Client/vendor/guzzlehttp/guzzle/src/functions_include.php')) {
            require_once $this->getPath() . '/Client/vendor/guzzlehttp/guzzle/src/functions_include.php';
        }

        // Load promises functions
        if (file_exists($this->getPath() . '/Client/vendor/guzzlehttp/promises/src/functions_include.php')) {
            require_once $this->getPath() . '/Client/vendor/guzzlehttp/promises/src/functions_include.php';
        }

        // Load psr7 functions
        if (file_exists($this->getPath() . '/Client/vendor/guzzlehttp/psr7/src/functions_include.php')) {
            require_once $this->getPath() . '/Client/vendor/guzzlehttp/psr7/src/functions_include.php';
        }

        // Load client
        if (file_exists($this->getPath() . '/Client/vendor/mollie/mollie-api-php/src/MollieApiClient.php')) {
            require_once $this->getPath() . '/Client/vendor/mollie/mollie-api-php/src/MollieApiClient.php';
        }
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
            $view->extendsTemplate('backend/mollie_extend_order/view/list/list.js');
            $view->extendsTemplate('backend/mollie_extend_order/controller/list.js');
            $view->extendsTemplate('backend/mollie_extend_order_detail/view/detail/position.js');
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
        $this->updateAttributes();

        parent::install($context);
    }

    /**
     * @param UpdateContext $context
     *
     * @throws Exception
     */
    public function update(UpdateContext $context)
    {
        $this->cleanBackendSnippets();

        // clear config cache
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);

        // create database tables
        $this->updateDbTables();

        // add extra attributes
        $this->updateAttributes();

        // set config value for upgraders from version 1.3
        if (substr($context->getPlugin()->getVersion(), 0, strlen('1.3')) == '1.3')
            $this->writeConfig($context->getPlugin(), 'orders_api_only_where_mandatory', 'no');

        parent::update($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        // Don't remove payment methods but set them to inactive.
        // So orders paid still reference an existing payment method

        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = $this->getPaymentMethodService();

        if ($paymentMethodService !== null) {
            // Deactivate all Mollie payment methods
            $paymentMethodService->deactivatePaymentMethods();
        }

        // remove extra attributes
        $this->removeAttributes();

        parent::uninstall($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = $this->getPaymentMethodService();

        if ($paymentMethodService !== null) {
            // Deactivate all Mollie payment methods
            $paymentMethodService->deactivatePaymentMethods();
        }

        parent::deactivate($context);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        // clear config cache
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);

        // update db tables
        $this->updateDbTables();

        /** @var null|array $methods */
        $methods = null;

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
            $paymentMethodService->installPaymentMethod($context->getPlugin()->getName(), $methods);
        }

        // download apple pay merchant domain verification file of mollie
        $downloader = new ApplePayDomainFileDownloader();
        $downloader->downloadDomainAssociationFile(Shopware()->DocPath());

        // add index to mol_sw_transactions if not exists
        $this->addIndexToTransactions();

        // cleanup old transaction ordermail variables
        $this->cleanOrdermailVariables();

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
                OrderLines::class
            ]);
        } catch (Exception $ex) {

            $this->getPluginLogger()->error(
                'Error when updating database tables',
                array(
                    'error' => $ex->getMessage(),
                )
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
        } catch (Exception $ex) {

            $this->getPluginLogger()->error(
                'Error when removing database tables',
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }
    }

    /**
     * Create a new Attributes object
     */
    protected function makeAttributes()
    {
        return new Attributes(
            $this->container->get('models'),
            $this->container->get('shopware_attribute.crud_service')
        );
    }

    /**
     * Update extra attributes
     */
    protected function updateAttributes()
    {
        try {
            $this->makeAttributes()->create([['s_order_basket_attributes', 'basket_item_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'basket_item_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'mollie_transaction_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'mollie_order_line_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'mollie_return', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_user_attributes', 'mollie_shopware_ideal_issuer', 'string', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_user_attributes', 'mollie_shopware_credit_card_token', 'string', []]]);
        } catch (Exception $ex) {
            //
        }
    }

    /**
     * Remove extra attributes
     */
    protected function removeAttributes()
    {
        try {
            $this->makeAttributes()->remove([['s_user_attributes', 'mollie_shopware_ideal_issuer']]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->remove([['s_user_attributes', 'mollie_shopware_credit_card_token']]);
        } catch (Exception $ex) {
            //
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
     * @return PaymentMethodService
     */
    protected function getPaymentMethodService()
    {
        /** @var ModelManager $modelManager */
        $modelManager = $this->container->get('models');

        /** @var MollieApiClient $mollieApiClient */
        $mollieApiClient = $this->getMollieApiClient();

        /** @var Plugin\PaymentInstaller $paymentInstaller */
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
     *
     */
    private function cleanBackendSnippets()
    {
        $connection = $this->container->get('dbal_connection');

        $iniFiles = array(
            new SnippetFile(
                'backend/mollie/general',
                __DIR__ . '/Resources/snippets/backend/mollie/general.ini'
            )
        );

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
}
