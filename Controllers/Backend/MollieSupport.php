<?php

use MollieShopware\MollieShopware;
use MollieShopware\Traits\Controllers\BackendControllerTrait;
use Shopware\Models\Payment\Payment;
use Psr\Log\LoggerInterface;

class Shopware_Controllers_Backend_MollieSupport extends Shopware_Controllers_Backend_Application
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
        $this->view->assign('version', MollieShopware::PLUGIN_VERSION);
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

        $this->view->assign('user', $user);
    }

    /**
     * Sends a support e-mail to mollie.
     *
     * @return void
     */
    public function sendEmailAction()
    {
        $this->loadServices();

        if (!$this->validateRequest($this->request)) {
            return;
        }

        $success = true;

        try {
            $this->mailTransport->send($this->createEmail($this->request));
        } catch (Zend_Mail_Transport_Exception $exception) {
            $this->logger->error(
                'Error when trying to send a support email to Mollie.',
                [
                    'error' => $exception->getMessage(),
                ]
            );

            $success = false;
        }

        $this->view->assign('data', [
            'success' => $success,
        ]);
    }

    /**
     * Loads the required services.
     *
     * @return void
     */
    private function loadServices()
    {
        $this->logger = $this->container->get('mollie_shopware.components.logger');
        $this->mailTransport = $this->container->get('shopware.mail_transport');
    }

    /**
     * Creates a Zend_Mail object based on the request.
     *
     * @return Zend_Mail|null
     */
    private function createEmail(Enlight_Controller_Request_RequestHttp $request)
    {
        $name = $request->get('name');
        $from = $request->get('email');
        $to = $request->get('to');
        $message = $request->get('message');

        $body = sprintf('<div style="font-family: sans-serif; font-size: 12pt;">%s</div>', $message);

        try {
            $email = (new Zend_Mail())
                ->addTo($to)
                ->setBodyHtml($body)
                ->setFrom($from, $name);
        } catch (Zend_Mail_Exception $exception) {
            $this->logger->error(
                'Error when trying to create a Zend_Mail object for sending a support email to Mollie.',
                [
                    'error' => $exception->getMessage(),
                ]
            );
        }

        return isset($email) ? $email : null;
    }

    /**
     * Validates if the expected fields in the
     * request are present and not empty.
     *
     * @param Enlight_Controller_Request_RequestHttp $request
     * @return bool
     */
    private function validateRequest(Enlight_Controller_Request_RequestHttp $request)
    {
        $isValid = !empty($request->get('name'))
            && !empty($request->get('email'))
            && !empty($request->get('to'))
            && !empty($request->get('message'));

        if ($isValid) {
            return true;
        }

        $this->view->assign('data', [
            'error' => 'Not all fields are set.',
            'success' => false,
        ]);

        return false;
    }
}
