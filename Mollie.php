<?php

namespace Mollie;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Payment\Payment;
use Doctrine\Common\Collections\ArrayCollection;
use Mollie_API_Client;
use Smarty;
use Enlight_Event_EventArgs;

class Mollie extends Plugin
{
    // /**
    //  * Load composer libraries
    //  */
    // public function afterInit()
    // {
    //     require_once $this->Path() . '/vendor/autoload.php';
    // }

    /**
     * Return Shopware events subscribed to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch' => 'onStartDispatch',
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Mollie' => 'registerController',

            // engine/Shopware/Core/sAdmin.php (method: sUpdatePayment line: 613)
            // Called when a user selects an other payment method
            'Shopware_Modules_Admin_UpdatePayment_FilterSql' => 'onUpdatePaymentForUser',

            // extend some backend ext.js files
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onOrderPostDispatch',

            // 'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendDispatch',
            
            'Theme_Compiler_Collect_Plugin_Javascript' => 'addJsFiles',
        ];
    }

    /**
     * Require composer libraries on a new request
     */
    public function onStartDispatch()
    {
    	// Load composer libraries
        if (file_exists($this->getPath() . '/vendor/autoload.php')) {
            require_once $this->getPath() . '/vendor/autoload.php';
        }
    }

    /**
     * Register Mollie controller
     */
    public function registerController(Enlight_Event_EventArgs $args)
    {
        return $this->getPath() . '/Controllers/Frontend/Mollie.php';
    }

    // public function onFrontendDispatch(Enlight_Event_EventArgs $args)
    // {
    //     // add template directory
    //     $this->container->get('Template')->addTemplateDir(
    //         $this->getPath() . '/Views/'
    //     );
    // }

    /**
     * Inject some backend ext.js extensions for the order module
     */
    public function onOrderPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();

        $view->addTemplateDir(__DIR__ . '/Views');

        if ($request->getActionName() == 'index')
        {
            //$view->extendsTemplate('backend/swag_extend_customer/app.js');
        }

        if ($request->getActionName() == 'load')
        {
            $view->extendsTemplate('backend/mollie_extend_order/view/list/list.js');
            $view->extendsTemplate('backend/mollie_extend_order/controller/list.js');
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function addJsFiles(Enlight_Event_EventArgs $args)
    {
        $jsFiles = [
            __DIR__ . '/Views/responsive/frontend/_public/src/js/ideal-issuers.js',
        ];

        return new ArrayCollection($jsFiles);
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
    	// Payments are not created at install,
    	// because the user hasn't had the ability to put in an API-key at this time
    	// 
    	// Payments are added on activation of the plugin
        // The user should put in an API key between install and activation


        // clear config cache
        $cacheManager = $this->container->get('shopware.cache_manager');
        $cacheManager->clearTemplateCache();

        return [
            'success' => true
        ];
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        // Don't remove payment methods but set them to inactive.
        // So orders paid still reference an existing payment method
        $this->deactivatePayments();
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->deactivatePayments();
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
    	// first set all payment methods to inactive
		// $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        $this->deactivatePayments();

        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $paymentOptions = $this->getPaymentOptions();

        foreach ($paymentOptions as $key => $options) 
        {
        	$installer->createOrUpdate($context->getPlugin(), $options);
        }
    }

    /**
     * Deactivate all Mollie payment methods
     */
    protected function deactivatePayments()
    {
        $em = $this->container->get('models');

        $qb = $em->createQueryBuilder();

        $query = $qb->update('Shopware\Models\Payment\Payment', 'p')
            ->set('p.active', '?1')
            ->where($qb->expr()->like('p.name', '?2'))
            ->setParameter(1, false)
            ->setParameter(2, 'mollie_%')
            ->getQuery();

        $query->execute();
    }

    /**
     * Get the current payment methods via the Mollie API
     * @return array[] $options
     */
    protected function getPaymentOptions()
    {
    	$mollie = $this->getMollieClient();

        // TODO: get methods in the correct locale (de_DE en_US es_ES fr_FR nl_BE fr_BE nl_NL)
    	$methods = $mollie->methods->all();

        $options = [];
        $position = 0;

        foreach ($methods as $key => $method) 
        {
            $smarty = new Smarty;
            $smarty->assign('method', $method);
            $smarty->assign('router', Shopware()->Router());

            // template path
            $template = __DIR__ . '/Resources/PaymentmethodViews/' . strtolower($method->id) . '.tpl';

            // set default template if no specific template exists
            if (!file_exists($template))
            {
                $template =  __DIR__ . '/Resources/PaymentmethodViews/main.tpl';
            }

            $additionalDescription = $smarty->fetch('file:' . $template);

            $options[] = [
                'name' => 'mollie_' . $method->id,
                'description' => $method->description,
                'action' => 'frontend/Mollie',
                'active' => 1,
                'position' => $position,
                'additionalDescription' => $additionalDescription
            ];
        }

        return $options;
    }

    /**
     * @return Mollie_API_Client
     */
    protected function getMollieClient()
    {
        require_once $this->getPath() . '/vendor/autoload.php';

		$apiKey = Shopware()->Config()->getByNamespace('Mollie', 'api-key');

		$mollie = new Mollie_API_Client;
		$mollie->setApiKey($apiKey);

		return $mollie;
    }

    /**
     * When a payment method is changed, the chosen payment method is saved on the user
     * For iDEAL an issuer should also be saved to the session
     */
    public function onUpdatePaymentForUser(Enlight_Event_EventArgs $args)
    {
        $query = $args->getReturn(); // "UPDATE s_user SET paymentID = ? WHERE id = ?";
        $sAdmin = $args->get('subject');
        $userId = $args->get('id');

        $issuer = Shopware()->Front()->Request()->getPost('mollie-ideal-issuer');

        // write issuer id to session
        $this->setIdealIssuer($issuer);

        return $query;
    }

    protected function setIdealIssuer($issuer)
    {
        Shopware()->Session()->sUserVariables['mollie-ideal-issuer'] = $issuer;

        return $issuer;
    }
}
