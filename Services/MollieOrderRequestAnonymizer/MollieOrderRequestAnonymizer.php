<?php

namespace MollieShopware\Services\MollieOrderRequestAnonymizer;


class MollieOrderRequestAnonymizer
{


    /**
     * Anonymizes the Mollie Orders API request data
     * https://docs.mollie.com/reference/v2/orders-api/create-order
     *
     * @param array<mixed> $requestData
     * @return array<mixed>
     */
    public function anonymize(array $requestData)
    {
        if (empty($requestData)) {
            return $requestData;
        }

        if (isset($requestData['billingAddress'])) {
            $requestData['billingAddress']['organizationName'] = '';
            $requestData['billingAddress']['streetAndNumber'] = '';
            $requestData['billingAddress']['givenName'] = '';
            $requestData['billingAddress']['familyName'] = '';
            $requestData['billingAddress']['email'] = '';
            $requestData['billingAddress']['phone'] = '';
        }

        if (isset($requestData['shippingAddress'])) {
            $requestData['shippingAddress']['organizationName'] = '';
            $requestData['shippingAddress']['streetAndNumber'] = '';
            $requestData['shippingAddress']['givenName'] = '';
            $requestData['shippingAddress']['familyName'] = '';
            $requestData['shippingAddress']['email'] = '';
            $requestData['shippingAddress']['phone'] = '';
        }

        return $requestData;
    }

}
