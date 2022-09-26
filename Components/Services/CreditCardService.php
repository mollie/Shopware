<?php

namespace MollieShopware\Components\Services;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Exceptions\CustomerNotFoundException;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Attribute\Customer as CustomerAttribute;
use Shopware\Models\Customer\Customer;

class CreditCardService
{
    /** @var MollieApiClient */
    protected $apiClient;

    /** @var CurrentCustomer */
    protected $customer;

    /** @var ModelManager */
    protected $modelManager;

    public function __construct(
        MollieApiClient $apiClient,
        CurrentCustomer $customer,
        ModelManager $modelManager
    ) {
        $this->apiClient = $apiClient;
        $this->customer = $customer;
        $this->modelManager = $modelManager;
    }

    public function setCardToken($cardToken)
    {
        $customerAttributes = null;

        /** @var Customer $customer */
        $customer = $this->customer->getCurrent();

        if ($customer !== null) {
            $customerAttributes = $customer->getAttribute();
        }

        if ($customerAttributes === null && class_exists(CustomerAttribute::class)) {
            $customerAttributes = new CustomerAttribute();
            $customerAttributes->setCustomer($customer);
        }

        if (method_exists($customerAttributes, 'setMollieShopwareCreditCardToken')) {
            $customerAttributes->setMollieShopwareCreditCardToken((string) $cardToken);
        }

        try {
            $this->modelManager->persist($customerAttributes);
            $this->modelManager->flush($customerAttributes);
        } catch (\Exception $e) {
            //
        }
        
        try {
            $customer->setAttribute($customerAttributes);
            $this->modelManager->persist($customer);
            $this->modelManager->flush($customer);
        } catch (\Exception $e) {
            //
        }
    }

    public function getCardToken()
    {
        /** @var null|Customer $customer */
        $customer = $this->customer->getCurrent();

        if ($customer === null) {
            throw new CustomerNotFoundException(
                'The current customer could not be found.'
            );
        }

        if (
            $customer->getAttribute() !== null &&
            method_exists($customer->getAttribute(), 'getMollieShopwareCreditCardToken')
        ) {
            return $customer->getAttribute()->getMollieShopwareCreditCardToken();
        }

        /**
         * In B2b a contact customer doesn't have attributes,
         * so take the attributes of the debtor user it belongs to
         */
        return $this->modelManager->getConnection()->fetchColumn('
            SELECT s_user_attributes.mollie_shopware_credit_card_token FROM s_user
            JOIN s_user_attributes ON (s_user.id = s_user_attributes.userID)
            WHERE s_user.customernumber = ?
            AND s_user_attributes.mollie_shopware_ideal_issuer IS NOT NULL
            LIMIT 1
        ', [$customer->getNumber()]);
    }
}
