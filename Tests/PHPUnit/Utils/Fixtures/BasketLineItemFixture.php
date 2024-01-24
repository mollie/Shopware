<?php

namespace MollieShopware\Tests\Utils\Fixtures;

use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;

class BasketLineItemFixture
{

    /**
     * @param float $unitPriceNet
     * @param int $quantity
     * @param int $taxRate
     * @return MollieBasketItem
     */
    public function buildProductItemNet($unitPriceNet, $quantity, $taxRate)
    {
        $item = new MollieBasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'Sample Product',
            round($unitPriceNet, 2),
            round($unitPriceNet, 2),
            $quantity,
            $taxRate,
            ''
        );

        $item->setIsGrossPrice(false);

        return $item;
    }

    /**
     * @param $unitPriceGross
     * @param $quantity
     * @param $taxRate
     * @return MollieBasketItem
     */
    public function buildProductItemGross($unitPriceGross, $quantity, $taxRate)
    {
        $netPrice = ($unitPriceGross / (100 + $taxRate)) * 100;

        $item = new MollieBasketItem(
            1560,
            55,
            'ART-55',
            0,
            0,
            'Sample Product',
            round($unitPriceGross, 2),
            round($netPrice, 2),
            $quantity,
            $taxRate,
            ''
        );

        $item->setIsGrossPrice(true);

        return $item;
    }
}
