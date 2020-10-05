<?php

namespace MollieShopware\Components\Shipping;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Enlight_Components_Session_Namespace;

class Shipping
{
    /**
     * @var \sAdmin
     */
    private $admin;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @param Connection $connection
     * @param Enlight_Components_Session_Namespace $session
     */
    public function __construct(Connection $connection, Enlight_Components_Session_Namespace $session)
    {
        # attention, modules doesnt exist in CLI
        $this->admin = Shopware()->Modules()->Admin();

        $this->connection = $connection;
        $this->session = $session;
    }


    /**
     * @param $countryID
     * @param $paymentID
     * @return array
     */
    public function getShippingMethods($countryID, $paymentID)
    {
        return $this->admin->sGetPremiumDispatches($countryID, $paymentID);
    }

    /**
     * @param $country
     * @param $shippingMethodId
     * @return array|int|int[]|mixed
     */
    public function getShippingMethodCosts($country, $shippingMethodId)
    {
        $previousDispatch = $this->getCartShippingMethodID();

        $this->setCartShippingMethodID($shippingMethodId);

        $costs = $this->admin->sGetPremiumShippingcosts($country);

        $this->setCartShippingMethodID($previousDispatch);

        if ($costs['value'] === null) {
            return 0;
        }

        return (float)$costs['value'];
    }

    /**
     * @param $shippingMethodId
     */
    public function setCartShippingMethodID($shippingMethodId)
    {
        $this->session['sDispatch'] = $shippingMethodId;
    }

    /**
     * @return mixed
     */
    public function getCartShippingMethodID()
    {
        return $this->session['sDispatch'];
    }

    /**
     * @return mixed
     */
    public function getCartShippingMethod()
    {
        $selectedID = $this->getCartShippingMethodID();

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')
            ->from('s_premium_dispatch')
            ->where($qb->expr()->eq('id', ':id'))
            ->setParameter(':id', $selectedID);

        $row = $qb->execute()->fetch();

        return $row;
    }

}
