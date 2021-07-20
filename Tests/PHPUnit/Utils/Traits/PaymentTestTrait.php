<?php

namespace MollieShopware\Tests\Utils\Traits;

use MollieShopware\Services\Mollie\Payments\Formatters\NumberFormatter;
use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;
use MollieShopware\Tests\Utils\Fixtures\PaymentAddressFixture;
use MollieShopware\Tests\Utils\Fixtures\PaymentLineItemFixture;


trait PaymentTestTrait
{

    /***
     * @return PaymentAddress
     */
    protected function getAddressFixture1()
    {
        return (new PaymentAddressFixture('Munich'))->buildAddress();
    }

    /***
     * @return PaymentAddress
     */
    protected function getAddressFixture2()
    {
        return (new PaymentAddressFixture('Amsterdam'))->buildAddress();
    }

    /**
     * @return PaymentLineItem
     */
    protected function getLineItemFixture()
    {
        return (new PaymentLineItemFixture())->buildItem();
    }

    /**
     * @param PaymentAddress $address
     * @return array
     */
    protected function getExpectedAddressStructure(PaymentAddress $address)
    {
        return [
            'title' => $address->getTitle(),
            'givenName' => $address->getGivenName(),
            'familyName' => $address->getFamilyName(),
            'email' => $address->getEmail(),
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getStreetAdditional(),
            'postalCode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountryIso2(),
        ];
    }

    /**
     * @param PaymentLineItem $item
     * @return array
     */
    public function getExpectedLineItemStructure(PaymentLineItem $item)
    {
        $formatter = new NumberFormatter();

        return [
            'type' => $item->getType(),
            'name' => $item->getName(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => [
                'currency' => $item->getCurrency(),
                'value' => $formatter->formatNumber($item->getUnitPrice()),
            ],
            'totalAmount' => [
                'currency' => $item->getCurrency(),
                'value' => $formatter->formatNumber($item->getTotalAmount()),
            ],
            'vatRate' => $formatter->formatNumber($item->getVatRate()),
            'vatAmount' => [
                'currency' => $item->getCurrency(),
                'value' => $formatter->formatNumber($item->getVatAmount()),
            ],
            'sku' => $item->getSku(),
            'imageUrl' => $item->getImageUrl(),
            'productUrl' => $item->getProductUrl(),
            'metadata' => $item->getMetadata(),
        ];
    }


}
