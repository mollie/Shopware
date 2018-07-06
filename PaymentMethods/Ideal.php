<?php

	// Mollie Shopware Plugin Version: 1.2.2

namespace MollieShopware\PaymentMethods;

use Mollie\Api\Types\PaymentMethod;
use Mollie\Api\MollieApiClient;
use MollieShopware\Components\CurrentCustomer;
use Shopware\Components\Model\ModelManager;

class Ideal
{
    /**
     * @var MollieApiClient
     */
    protected $mollieApi;

    /**
     * @var CurrentCustomer
     */
    protected $customer;

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $em;

    public function __construct(MollieApiClient $mollieApi, CurrentCustomer $customer, ModelManager $em)
    {
        $this->mollieApi = $mollieApi;
        $this->customer = $customer;
        $this->em = $em;
    }

    public function getIssuers()
    {

        $payment_methods = $this->mollieApi->methods->all(['include'=>'issuers']);

        $idealIssuers = [];

        foreach($payment_methods as $paymentMethod) {

            if ($paymentMethod->id === 'ideal'){
                $issuers = $paymentMethod->issuers();

                foreach ($issuers as $key => $issuer) {

                    if ($issuer->id === $this->getSelectedIssuer()) {
                        $issuer->isSelected = true;
                    }

                    $idealIssuers[] = $issuer;
                }
            }
        }


        return $idealIssuers;
    }

    /**
     * Set the id of the chosen ideal issuer in the database
     */
    public function setSelectedIssuer($issuer)
    {
        $customer = $this->customer->getCurrent();

        if (empty($customer)) {
            return;
        }

        $attributes = $customer->getAttribute();

        if (empty($attributes)) {
            return;
        }

        $attributes->setMollieShopwareIdealIssuer($issuer);

        $this->em->persist($attributes);
        $this->em->flush();

        return $issuer;
    }

    /**
     * Get the id of the chosen ideal issuer from database
     */
    public function getSelectedIssuer()
    {
        $customer = $this->customer->getCurrent();

        if (empty($customer)) {
            return '';
        }

        $attributes = $customer->getAttribute();

        if (!empty($attributes)) {
            return $attributes->getMollieShopwareIdealIssuer();
        }

        /**
         * In B2b a contact customer doesn't have attributes,
         * so take the attributes of the debtor user it belongs to
         */
        $issuer = $this->em->getConnection()->fetchColumn('
            SELECT s_user_attributes.mollie_shopware_ideal_issuer FROM s_user
            JOIN s_user_attributes ON (s_user.id = s_user_attributes.userID)
            WHERE s_user.customernumber = ?
            AND s_user_attributes.mollie_shopware_ideal_issuer IS NOT NULL
            LIMIT 1
        ', [ $customer->getNumber() ]);

        return empty($issuer) ? '' : $issuer;
    }
}
