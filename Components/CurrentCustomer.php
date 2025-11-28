<?php

namespace MollieShopware\Components;

use ArrayObject;
use Psr\Log\LoggerInterface;
use Shopware\Models\Customer\Customer;
use function sprintf;

class CurrentCustomer
{
    /** @var \Enlight_Components_Session_Namespace */
    protected $session;

    /** @var \Shopware\Components\Model\ModelManager */
    protected $modelManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        \Enlight_Components_Session_Namespace $session,
        \Shopware\Components\Model\ModelManager $modelManager,
        LoggerInterface $logger
    ) {
        $this->session = $session;
        $this->modelManager = $modelManager;
        $this->logger = $logger;
    }

    /**
     * Get the id of the currently logged in user
     *
     * @return int
     */
    public function getCurrentId()
    {
        /** @var null|numeric-string $customerId */
        $customerId = $this->session->offsetGet('sUserId');
        if (!empty($customerId)) {
            return (int)$customerId;
        }

        $this->logger->error('sUserId not set in session');

        /** @var null|int $customerId */
        $customerId = $this->session->offsetGet('auto-user');
        if (!empty($customerId)) {
            return (int)$customerId;
        }

        $this->logger->error('auto-user not set in session');

        /** @var null|ArrayObject $sOrderVariables */
        $sOrderVariables = $this->session->offsetGet('sOrderVariables');
        if (!($sOrderVariables instanceof ArrayObject)) {
            $this->logger->error('sOrderVariables not set in session');

            return 0;
        }

        /** @var null|array $sUserData */
        $sUserData = $sOrderVariables->offsetGet('sUserData');
        if ($sUserData === null) {
            $this->logger->error('sUserData not set in session');

            return 0;
        }

        $customerId = isset($sUserData['additional']['user']['id']) ? $sUserData['additional']['user']['id'] : 0;
        if (empty($customerId)) {
            $this->logger->error('sUserData does not contain a user id');

            return 0;
        }

        return (int)$customerId;
    }

    /**
     * Get the current customer
     *
     * @return null|\Shopware\Models\Customer\Customer
     */
    public function getCurrent()
    {
        /** @var int $customerId */
        $customerId = $this->getCurrentId();

        if ($customerId === 0) {
            $this->logger->error('no customer id found');

            return null;
        }

        /** @var null|\Shopware\Models\Customer\Customer $customer */
        $customer = $this->modelManager->getRepository(
            \Shopware\Models\Customer\Customer::class
        )->find($customerId);

        if ($customer instanceof Customer) {
            return $customer;
        }

        $this->logger->error(sprintf('customer with id "%d" not found', $customerId));

        return null;
    }
}
