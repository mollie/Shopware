<?php

// Mollie Shopware Plugin Version: 1.4.4

namespace MollieShopware\Components;

class CurrentCustomer
{
    /** @var \Enlight_Components_Session_Namespace */
    protected $session;

    /** @var \Shopware\Components\Model\ModelManager */
    protected $modelManager;

    public function __construct(
        \Enlight_Components_Session_Namespace $session,
        \Shopware\Components\Model\ModelManager $modelManager)
    {
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
     * @return \Shopware\Models\Customer\Customer|null
     */
    public function getCurrent()
    {
        $userId = $this->getCurrentId();

        if (empty($userId))
            return null;

        /** @var \Shopware\Models\Customer\Customer $customer */
        $customer = $this->modelManager->getRepository(
            \Shopware\Models\Customer\Customer::class
        )->find($userId);

        return $customer;
    }

    /**
     * Get attributes array for current customer
     *
     * @return array
     */
    public function getCurrentArray()
    {
        $userId = $this->getCurrentId();

        if (empty($userId)) {
            return [];
        }

        $user = $this->em->getConnection()->fetchAssoc('
            SELECT * FROM s_user
            WHERE id = ?
        ', [ $this->getCurrentId() ]);

        $attribute = $this->em->getConnection()->fetchAssoc('
            SELECT * FROM s_user_attributes
            WHERE userID = ?
        ', [ $this->getCurrentId() ]);

        $user['attribute'] = $attribute;

        return $user;
    }

    /**
     * Get whether the current customer is logged in
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        return !empty($this->session->sUserId);
    }
}
