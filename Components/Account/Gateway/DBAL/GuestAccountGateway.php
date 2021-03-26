<?php

namespace MollieShopware\Components\Account\Gateway\DBAL;

use Doctrine\ORM\EntityManagerInterface;
use MollieShopware\Components\Account\Gateway\GuestAccountGatewayInterface;
use Shopware\Bundle\AccountBundle\Form\Account\AddressFormType;
use Shopware\Bundle\AccountBundle\Form\Account\PersonalFormType;
use Shopware\Bundle\AccountBundle\Service\AddressServiceInterface;
use Shopware\Bundle\AccountBundle\Service\RegisterServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Shop;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Payment\Payment;
use Symfony\Component\Form\FormFactoryInterface;

class GuestAccountGateway implements GuestAccountGatewayInterface
{

    /** @var AddressServiceInterface $addressService */
    private $addressService;

    /** @var EntityManagerInterface $em */
    private $em;

    /** @var FormFactoryInterface $formFactory */
    private $formFactory;

    private $modules;

    /** @var RegisterServiceInterface $registerService */
    private $registerService;

    /** @var Shop $shop */
    private $shop;

    public function __construct(
        AddressServiceInterface $addressService,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory,
        ContextServiceInterface $contextService,
        RegisterServiceInterface $registerService
    ) {
        $this->addressService = $addressService;
        $this->em = $em;
        $this->formFactory = $formFactory;

        # attention, modules doesnt exist in CLI
        $this->modules = Shopware()->Modules();

        $this->registerService = $registerService;
        $this->shop = $contextService->getShopContext()->getShop();
    }

    /**
     * @return mixed
     */
    public function getPaymentMeanId()
    {
        $paymentMean = $this->em->getRepository(Payment::class)->findOneBy(
            [
                'name' => 'mollie_applepaydirect',
            ]
        );

        return $paymentMean->getId();
    }

    /**
     * @param $paymentMeanId
     * @return mixed
     */
    public function getPaymentMeanById($paymentMeanId)
    {
        return $this->modules->Admin()->sGetPaymentMeanById($paymentMeanId);
    }

    /**
     * @param string $email
     * @return mixed
     */
    public function getPasswordMD5($email)
    {
        $qb = $this->em->createQueryBuilder();

        $qb->select('c.hashPassword')
            ->from(Customer::class, 'c')
            ->where($qb->expr()->like('c.email', ':email'))
            ->andWhere($qb->expr()->eq('c.active', 1))
            ->andWhere($qb->expr()->eq('c.accountMode', Customer::ACCOUNT_MODE_FAST_LOGIN))
            ->setParameter(':email', $email);

        if ($this->shop->hasCustomerScope()) {
            $qb->andWhere($qb->expr()->eq('c.shopId', $this->shop->getId()));
        }

        # Always use the latest account. It is possible, that the account already exists but the password may be invalid.
        # The plugin then creates a new account and uses that one instead.
        $qb->orderBy('c.id', 'DESC');

        return $qb->getQuery()->getArrayResult()[0]['hashPassword'];
    }

    /**
     * @param $userId
     * @param $shippingData
     * @return mixed|void
     */
    public function updateShipping($userId, $shippingData)
    {
        /** @var Customer $customer */
        $customer = $this->em->getRepository(Customer::class)->findOneBy(['id' => $userId]);

        /** @var Address $address */
        $address = $customer->getDefaultShippingAddress();

        $form = $this->formFactory->create(AddressFormType::class, $address);
        $form->submit($shippingData);

        $this->addressService->update($address);
    }

    /**
     * @param $auth
     * @param $shipping
     */
    public function saveUser($auth, $shipping)
    {
        $plain = array_merge($auth, $shipping);

        //Create forms and validate the input
        $customer = new Customer();
        $form = $this->formFactory->create(PersonalFormType::class, $customer);
        $form->submit($plain);

        $address = new Address();
        $form = $this->formFactory->create(AddressFormType::class, $address);
        $form->submit($plain);

        $this->registerService->register($this->shop, $customer, $address, $address);
    }

    /**
     * @param $email
     * @return mixed
     */
    public function getGuest($email)
    {
        $qb = $this->em->createQueryBuilder();

        $qb->select('c')
            ->from(Customer::class, 'c')
            ->where($qb->expr()->like('c.email', ':email'))
            ->andWhere($qb->expr()->eq('c.active', 1))
            ->andWhere($qb->expr()->eq('c.accountMode', 1))
            ->setParameter(':email', $email);

        if ($this->shop->hasCustomerScope()) {
            $qb->andWhere($qb->expr()->eq('c.shopId', $this->shop->getId()));
        }

        # Always use the latest account. It is possible, that the account already exists but the password may be invalid.
        # The plugin then creates a new account and uses that one instead.
        $qb->orderBy('c.id', 'DESC');

        return $qb->getQuery()->getArrayResult()[0];
    }
}
