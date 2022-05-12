<?php

use MollieShopware\Components\Support\EmailBuilder;
use MollieShopware\MollieShopware;
use MollieShopware\Traits\Controllers\BackendControllerTrait;
use Psr\Log\LoggerInterface;
use Shopware\Components\ShopwareReleaseStruct;
use Shopware\Models\Payment\Payment;

class Shopware_Controllers_Backend_MollieSupport extends Shopware_Controllers_Backend_Application
{
    use BackendControllerTrait;

    /**
     * @var string
     */
    protected $model = Payment::class;

    /**
     * @var EmailBuilder
     */
    private $emailBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Zend_Mail_Transport_Abstract
     */
    private $mailTransport;

    /**
     * Returns the version of the Mollie plugin.
     *
     * @return void
     */
    public function pluginVersionAction()
    {
        $this->view->assign('data', [
            'version' => MollieShopware::PLUGIN_VERSION,
        ]);
    }

    /**
     * Returns the version of Shopware.
     *
     * @return void
     */
    public function shopwareVersionAction()
    {
        try {
            $shopwareVersion = Shopware()->Config()->get('Version');

            # this parameter has been deprecated
            # we need a new version access for shopware 5.5 and up.
            # deprecated to be removed in 5.6
            if ($shopwareVersion === '___VERSION___') {
                /** @var ShopwareReleaseStruct $release */
                $release = Shopware()->Container()->get('shopware.release');
                $shopwareVersion = $release->getVersion();
            }
        } catch (Exception $exception) {
            $shopwareVersion = '5.x.x';
        }

        $this->view->assign('data', [
            'version' => $shopwareVersion,
        ]);
    }

    /**
     * Returns an array of data for the user that
     * is currently logged into the backend.
     *
     * @return void
     */
    public function loggedInUserAction()
    {
        $identity = $this->container->get('auth')->getIdentity();

        $user = [
            'username' => isset($identity->username) ? $identity->username : '',
            'fullName' => isset($identity->name) ? $identity->name : '',
            'emailAddress' => isset($identity->email) ? $identity->email : '',
        ];

        $this->view->assign('data', [
            'user' => $user,
        ]);
    }

    /**
     * Sends a support e-mail to mollie.
     *
     * @return void
     */
    public function sendEmailAction()
    {
        $this->loadServices();

        try {
            $email = $this->emailBuilder
                ->setFullName($this->request->get('name'))
                ->setEmailAddress($this->request->get('email'))
                ->setRecipientEmailAddress($this->request->get('to'))
                ->setMessage($this->request->get('message'))
                ->getEmail();
        } catch (Exception $exception) {
            $this->returnException($exception);
            return;
        }

        try {
            $this->mailTransport->send($email);
        } catch (Exception $exception) {
            $this->returnException($exception);
            return;
        }

        $this->view->assign('success', true);
    }

    /**
     * Loads the required services.
     *
     * @return void
     */
    private function loadServices()
    {
        $this->emailBuilder = $this->container->get('mollie_shopware.components.support.email_builder');
        $this->logger = $this->container->get('mollie_shopware.components.logger');
        $this->mailTransport = $this->container->get('shopware.mail_transport');
    }

    /**
     * @param Exception $exception
     * @return void
     */
    private function returnException(Exception $exception)
    {
        $this->view->assign('error', $exception->getMessage());
    }
}
