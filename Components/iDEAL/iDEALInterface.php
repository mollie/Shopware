<?php

namespace MollieShopware\Components\iDEAL;

use Mollie\Api\Resources\Issuer;
use Shopware\Models\Customer\Customer;

interface iDEALInterface
{

    /**
     * @return Issuer[]
     */
    public function getIssuers(Customer $customer);

    /**
     * @param Customer $customer
     * @return string
     */
    public function getCustomerIssuer(Customer $customer);

    /**
     * @param Customer $customer
     * @param string $issuer
     * @return void
     */
    public function updateCustomerIssuer(Customer $customer, $issuer);
}
