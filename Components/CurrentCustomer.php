<?php

namespace MollieShopware\Components;

class CurrentCustomer
{
    /** @var \Enlight_Components_Session_Namespace */
    protected $session;

    /** @var \Shopware\Components\Model\ModelManager */
    protected $modelManager;

    public function __construct(
        \Enlight_Components_Session_Namespace $session,
        \Shopware\Components\Model\ModelManager $modelManager
    ) {
        $this->session = $session;
        $this->modelManager = $modelManager;
    }

    /**
     * Get the id of the currently logged in user
     *
     * @return int
     */
    public function getCurrentId()
    {
        return !empty($this->session->sUserId) ? $this->session->sUserId : $this->session->offsetGet('auto-user');
    }

    /**
     * Get the current customer
     *
     * @return null|\Shopware\Models\Customer\Customer
     */
    public function getCurrent()
    {
        $userId = $this->getCurrentId();

        if (empty($userId)) {
            return null;
        }

        /** @var \Shopware\Models\Customer\Customer $customer */
        $customer = $this->modelManager->getRepository(
            \Shopware\Models\Customer\Customer::class
        )->find($userId);

        return $customer;
    }
}
