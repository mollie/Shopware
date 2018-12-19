<?php

	// Mollie Shopware Plugin Version: 1.3.10

namespace MollieShopware\Components;

use Shopware\Components\Model\ModelManager;
use Shopware\Models\Customer\Customer as CustomerModel;
use Enlight_Components_Session_Namespace;

class CurrentCustomer
{
    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    public function __construct(Enlight_Components_Session_Namespace $session, ModelManager $em)
    {
        $this->session = $session;
        $this->em = $em;
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
     * @return Shopware\Models\Customer\Customer
     */
    public function getCurrent()
    {
        $userId = $this->getCurrentId();

        if (empty($userId)) {
            return null;
        }

        return $this->em
            ->getRepository(CustomerModel::class)
            ->find($userId);
    }

    /**
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
     * @return boolean
     */
    public function isLoggedIn()
    {
        return !empty($this->session->sUserId);
    }
}
