<?php

namespace MollieShopware\Components\Account;

use MollieShopware\Components\Account\Exception\RegistrationMissingFieldException;
use MollieShopware\Components\Account\Gateway\GuestAccountGatewayInterface;
use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use Shopware\Components\Api\Exception\ValidationException;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Password\Manager;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;
use Shopware_Components_Config;
use Symfony\Component\Validator\ConstraintViolation;
use Throwable;

class Account
{

    /**
     * @var \sAdmin
     */
    private $admin;

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var Manager $encoder
     */
    private $pwdEncoder;

    /**
     * @var GuestAccountGatewayInterface
     */
    private $gwGuestCustomer;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @param \Enlight_Components_Session_Namespace $session
     * @param Manager $pwdEncoder
     * @param GuestAccountGatewayInterface $gwGuestCustomer
     * @param ModelManager $modelManager
     */
    public function __construct(\Enlight_Components_Session_Namespace $session, Manager $pwdEncoder, GuestAccountGatewayInterface $gwGuestCustomer, ModelManager $modelManager, Shopware_Components_Config $config)
    {
        # attention, modules doesnt exist in CLI
        $this->admin = Shopware()->Modules()->sAdmin();

        $this->session = $session;
        $this->pwdEncoder = $pwdEncoder;
        $this->gwGuestCustomer = $gwGuestCustomer;
        $this->modelManager = $modelManager;
        $this->config = $config;
    }


    /**
     * @param string $email
     */
    public function loginAccount($email)
    {
        # now load the guest account
        # and set the id as "current" user for the next request
        $guest = $this->getGuestAccount($email);

        $this->session->offsetSet('sUserId', $guest['id']);
        $this->session->offsetSet('sUserMail', $guest['email']);
        $this->session->offsetSet('sUserPassword', $guest['hashPassword']);
    }

    /**
     * Gets if the user is already signed in.
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        $userId = $this->session->offsetGet('sUserId');

        return !empty($userId);
    }

    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @param string $street
     * @param string $zip
     * @param string $city
     * @param int $countryID
     * @param string $phone
     *
     * @throws \Enlight_Exception
     * @throws RegistrationMissingFieldException
     */
    public function createGuestAccount($email, $firstname, $lastname, $street, $zip, $city, $countryID, $phone)
    {
        try {
            $data['auth']['accountmode'] = '1';

            $data['auth']['email'] = $email;
            $data['auth']['password'] = $email; # just use email for this
            $data['auth']['passwordMD5'] = $this->gwGuestCustomer->getPasswordMD5($email);

            $data['billing']['company'] = '';
            $data['billing']['salutation'] = 'mr';
            $data['billing']['firstname'] = $firstname;
            $data['billing']['lastname'] = $lastname;
            $data['billing']['customer_type'] = 'private';
            $data['billing']['department'] = '';

            $data['billing']['street'] = $street;
            $data['billing']['streetnumber'] = '';
            $data['billing']['zipcode'] = $zip;
            $data['billing']['city'] = $city;
            $data['billing']['stateID'] = '';
            $data['billing']['country'] = $countryID;

            $data['billing']['phone'] = $phone;

            $paymentMeanId = $this->gwGuestCustomer->getPaymentMeanId();

            $data['payment']['object'] = $this->gwGuestCustomer->getPaymentMeanById($paymentMeanId);


            $data['shipping'] = $data['billing'];

            // First try login / Reuse apple pay account
            $this->tryLogin($data['auth']);


            // Check login status
            if ($this->admin->sCheckUser()) {
                $this->gwGuestCustomer->updateShipping($this->session->offsetGet('sUserId'), $data['shipping']);

                $this->admin->sSYSTEM->_POST = ['sPayment' => $paymentMeanId];
                $this->admin->sUpdatePayment();
            } else {
                $encoderName = $this->pwdEncoder->getDefaultPasswordEncoderName();
                $data['auth']['encoderName'] = $encoderName;
                $data['auth']['password'] = $this->pwdEncoder->getEncoderByName($encoderName)->encodePassword($data['auth']['password']);

                $this->session->offsetSet('sRegisterFinished', false);

                $this->gwGuestCustomer->saveUser($data['auth'], $data['shipping']);

                $this->tryLogin($data['auth']);
            }
        } catch (ValidationException $ex) {

            # somehow we are still signed in as guest
            # so always make sure to completely logout
            $this->admin->logout();

            # now extract the field that leads to
            # a constraint violation (propertyPath)
            # just grab the first one, always existing as far as I know
            /** @var ConstraintViolation $violation */
            $violation = $ex->getViolations()[0];
            $field = $violation->getPropertyPath();

            throw new RegistrationMissingFieldException($field);
        }
    }

    /**
     * @param $email
     * @return mixed
     */
    public function getGuestAccount($email)
    {
        return $this->gwGuestCustomer->getGuest($email);
    }

    /**
     * @param $authData
     * @throws \Exception
     */
    private function tryLogin($authData)
    {
        $this->admin->sSYSTEM->_POST = $authData;

        $this->admin->sLogin(true);
    }

    /**
     * @param int $customerId
     * @param int $paymentId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateCustomerDefaultPaymentMethod($customerId, $paymentId)
    {
        $repository = $this->modelManager->getRepository(Customer::class);
        $customer = $repository->find($customerId);

        $customer->setPaymentId($paymentId);

        $this->modelManager->persist($customer);
        $this->modelManager->flush();
    }

    /**
     * @param int $customerId
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @return null|mixed
     */
    public function getCustomerDefaultNonApplePayPaymentMethod($customerId)
    {
        $paymentId = $this->config->get('defaultpayment');

        $queryBuilder = $this->modelManager->createQueryBuilder();
        $expr = $this->modelManager->getExpressionBuilder();

        $queryBuilder
            ->select('orders.paymentId')
            ->from(Order::class, 'orders')
            ->leftJoin('orders.payment', 'payment')
            ->where('orders.customerId = :customerId')
            ->andWhere($expr->neq('payment.name', ':applePayName'))
            ->andWhere($expr->neq('payment.name', ':applePayDirectName'))
            ->orderBy('orders.id', 'DESC')
            ->setParameter('customerId', $customerId)
            ->setParameter('applePayName', ShopwarePaymentMethod::APPLEPAY)
            ->setParameter('applePayDirectName', ShopwarePaymentMethod::APPLEPAYDIRECT)
            ->setMaxResults(1);

        try {
            $customerOrders = $queryBuilder->getQuery()->getSingleResult();
        } catch (Throwable $e) {
            return (int)$paymentId;
        }

        if (!empty($customerOrders)) {
            $paymentId = $customerOrders['paymentId'];
        }

        return (int)$paymentId;
    }
}
