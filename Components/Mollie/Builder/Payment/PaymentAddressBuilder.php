<?php

namespace MollieShopware\Components\Mollie\Builder\Payment;

use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;

class PaymentAddressBuilder
{

    /**
     * @param Address $address
     * @param Customer $customer
     * @return PaymentAddress
     */
    public function getPaymentAddress($address, Customer $customer)
    {
        $country = $address->getCountry();

        return new PaymentAddress(
            $address->getSalutation() . '.',
            (string)$address->getFirstName(),
            (string)$address->getLastName(),
            (string)$customer->getEmail(),
            (string)$address->getCompany(),
            (string)$address->getStreet(),
            (string)$address->getAdditionalAddressLine1(),
            (string)$address->getZipCode(),
            (string)$address->getCity(),
            $country ? (string)$country->getIso() : 'NL'
        );
    }
}
