<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Models\Voucher\VoucherType;
use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Exceptions\ApiNotSupportedException;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;


class Voucher extends AbstractPayment implements PaymentInterface
{

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'voucher'
        );
    }

    /**
     * @return mixed[]|void
     * @throws ApiNotSupportedException
     */
    public function buildBodyPaymentsAPI()
    {
        throw new ApiNotSupportedException('Voucher does not support the Payments API!');
    }

    /**
     * @return array<mixed>
     */
    public function buildBodyOrdersAPI()
    {
        $data = parent::buildBodyOrdersAPI();

        # add the category as mentioned here
        # https://docs.mollie.com/reference/v2/orders-api/create-order

        # we iterate through our mollie lines
        # and try to figure out if they have a category.
        # the category information is in the payment line item, but not yet in our mollie data.
        # if we find a value, only then we add it to the array.
        foreach ($data['lines'] as &$line) {

            # use name + quantity as identifier (price makes problems if format is different on server)
            $lineItemKey = md5($line['name'] . $line['quantity']);

            $category = $this->searchCategory($lineItemKey);

            if (!empty($category)) {
                $line['category'] = $category;
            }
        }

        return $data;
    }


    /**
     * @param string $searchKey
     * @return string
     */
    private function searchCategory($searchKey)
    {
        /** @var PaymentLineItem $lineItem */
        foreach ($this->getLineItems() as $lineItem) {

            # use name + quantity as identifier (price makes problems if format is different on server)
            $key = md5($lineItem->getName() . $lineItem->getQuantity());

            # if we have found the correct entry
            # then extract the category value for the mollie array
            if ($searchKey === $key) {
                return VoucherType::getMollieCategory($lineItem->getVoucherType());
            }
        }

        return '';
    }

}
