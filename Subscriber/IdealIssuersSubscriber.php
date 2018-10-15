<?php

<<<<<<< HEAD
	// Mollie Shopware Plugin Version: 1.3.1
=======
	// Mollie Shopware Plugin Version: 1.3.2
>>>>>>> order_position_fix

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Controller_Front;
use Enlight_Controller_ActionEventArgs;
use Mollie\Api\Exceptions\ApiException;
use MollieShopware\PaymentMethods\Ideal;

class IdealIssuersSubscriber implements SubscriberInterface
{
    /**
     * @var \MollieShopware\PaymentMethods\Ideal
     */
    protected $ideal;

    public function __construct(Ideal $ideal)
    {
        $this->ideal = $ideal;
    }

    public static function getSubscribedEvents()
    {
        return [
            // engine/Shopware/Core/sAdmin.php (method: sUpdatePayment line: 613)
            // Called when a user selects an other payment method
            'Shopware_Modules_Admin_UpdatePayment_FilterSql' => 'onUpdatePaymentForUser',

            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onChoosePaymentDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Account' => 'onChoosePaymentDispatch',
        ];
    }

    public function onChoosePaymentDispatch(Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();

        try {
            $idealIssuers = $this->ideal->getIssuers();

            $view->assign('mollieIdealIssuers', $idealIssuers);
            $view->assign('mollieIssues', false);
        }
        catch(ApiException $e){
            // API authentication issues
            $view->assign('mollieIssues', true);
        }
        catch(\Exception $e){
            // Some other issue with ideal
            $view->assign('mollieIssues', true);
        }
        $view->addTemplateDir(__DIR__ . '/Views');
    }

    /**
     * When a payment method is changed, the chosen payment method is saved on the user
     * For iDEAL an issuer should also be saved to the database
     */
    public function onUpdatePaymentForUser(Enlight_Event_EventArgs $args)
    {
        $query = $args->getReturn(); // "UPDATE s_user SET paymentID = ? WHERE id = ?";
        $sAdmin = $args->get('subject');
        $userId = $args->get('id');

        $issuer = Shopware()->Front()->Request()->getPost('mollie-ideal-issuer');

        // write issuer id to database
        $this->ideal->setSelectedIssuer($issuer);

        return $query;
    }
}
