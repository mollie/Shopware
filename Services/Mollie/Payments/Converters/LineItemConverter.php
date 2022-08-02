<?php

namespace MollieShopware\Services\Mollie\Payments\Converters;

use MollieShopware\Services\Mollie\Payments\Formatters\NumberFormatter;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;

class LineItemConverter
{

    /**
     * @var NumberFormatter
     */
    private $formatter;


    /**
     */
    public function __construct()
    {
        $this->formatter = new NumberFormatter();
    }

    /**
     * @param PaymentLineItem $item
     * @return mixed[]
     */
    public function convertItem(PaymentLineItem $item)
    {
        $data = [
            'type' => (string)$item->getType(),
            'name' => (string)$item->getName(),
            'quantity' => (int)$item->getQuantity(),
            'unitPrice' => [
                'currency' => (string)$item->getCurrency(),
                'value' => (string)$this->formatter->formatNumber($item->getUnitPrice()),
            ],
            'totalAmount' => [
                'currency' => (string)$item->getCurrency(),
                'value' => (string)$this->formatter->formatNumber($item->getTotalAmount()),
            ],
            'vatRate' => (string)$this->formatter->formatNumber($item->getVatRate()),
            'vatAmount' => [
                'currency' => (string)$item->getCurrency(),
                'value' => (string)$this->formatter->formatNumber($item->getVatAmount()),
            ],
            'sku' => (string)$item->getSku(),
            'imageUrl' => (string)$item->getImageUrl(),
            'productUrl' => (string)$item->getProductUrl(),
            'metadata' => (string)$item->getMetadata(),
        ];

        return $data;
    }
}
