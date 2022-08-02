<?php

namespace MollieShopware\Components\iDEAL;

use Mollie\Api\Resources\Issuer;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Gateways\MollieGatewayInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Customer\Customer;

class iDEAL implements iDEALInterface
{

    /**
     * @var MollieGatewayInterface
     */
    protected $gwMollie;

    /**
     * @var ModelManager
     */
    protected $modelManager;


    /**
     * iDEAL constructor.
     * @param MollieGatewayInterface $mollieGateway
     * @param ModelManager $modelManager
     */
    public function __construct(MollieGatewayInterface $mollieGateway, ModelManager $modelManager)
    {
        $this->gwMollie = $mollieGateway;
        $this->modelManager = $modelManager;
    }

    /**
     * @return Issuer[]
     */
    public function getIssuers(Customer $customer)
    {
        /** @var Issuer[] $issuers */
        $issuers = $this->gwMollie->getIdealIssuers();

        # we have to iterate through all issuers
        # and mark the currently selected one also
        # as selected within the list
        $customerIssuer = $this->getCustomerIssuer($customer);

        foreach ($issuers as &$issuer) {
            if ($issuer->id === $customerIssuer) {
                $issuer->isSelected = true;
                break;
            }
        }

        return $issuers;
    }

    /**
     * @param Customer $customer
     * @return string
     */
    public function getCustomerIssuer(Customer $customer)
    {
        $attributes = $customer->getAttribute();

        if (!empty($attributes)) {
            return (string)$attributes->getMollieShopwareIdealIssuer();
        }

        $sql = 'SELECT s_user_attributes.mollie_shopware_ideal_issuer 
            FROM s_user
            JOIN s_user_attributes ON (s_user.id = s_user_attributes.userID)
            WHERE s_user.customernumber = ?
            AND s_user_attributes.mollie_shopware_ideal_issuer IS NOT NULL
            LIMIT 1';

        /**
         * In B2b a contact customer doesn't have attributes,
         * so take the attributes of the debtor user it belongs to
         */
        $issuer = $this->modelManager->getConnection()->fetchColumn($sql, [$customer->getNumber()]);

        if (empty($issuer)) {
            return '';
        }

        return $issuer;
    }

    /**
     * @param Customer $customer
     * @param string $issuer
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @return void
     */
    public function updateCustomerIssuer(Customer $customer, $issuer)
    {
        $attributes = $customer->getAttribute();

        if (empty($attributes)) {
            return;
        }

        $attributes->setMollieShopwareIdealIssuer($issuer);

        $this->modelManager->persist($attributes);
        $this->modelManager->flush($attributes);
    }
}
