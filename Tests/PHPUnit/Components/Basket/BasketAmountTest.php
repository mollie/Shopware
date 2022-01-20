<?php

namespace MollieShopware\Tests\Components\Basket;

use MollieShopware\Components\Basket\BasketAmount;
use PHPUnit\Framework\TestCase;

class BasketAmountTest extends TestCase
{
    /**
     * This test verifies that an amount can be set on the BasketAmount DTO.
     *
     * @testdox Method setAmount sets an amount on the BasketAmount DTO.
     */
    public function testCanSetAmountToBasketAmount()
    {
        $test = new \Enlight_Collection_ArrayCollection(['view' => '12345']);

        $expectedAmount = 15.0;

        $basketAmount = new BasketAmount();
        $basketAmount->setAmount($expectedAmount);

        self::assertSame($expectedAmount, $basketAmount->getAmount());
    }

    /**
     * This test verifies that a currency can be set on the BasketAmount DTO.
     *
     * @testdox Method setCurrency sets a currency on the BasketAmount DTO.
     */
    public function testCanSetCurrencyToBasketAmount()
    {
        $expectedCurrency = 'EUR';

        $basketAmount = new BasketAmount();
        $basketAmount->setCurrency($expectedCurrency);

        self::assertSame($expectedCurrency, $basketAmount->getCurrency());
    }

    /**
     * This test verifies that an amount and a currency can be set on the BasketAmount DTO
     * through the constructor.
     *
     * @testdox Constructor sets an amount and a currency on the BasketAmount DTO.
     */
    public function testCanSetAmountAndCurrencyInConstructor()
    {
        $expectedAmount = 25.5;
        $expectedCurrency = 'USD';

        $basketAmount = new BasketAmount($expectedAmount, $expectedCurrency);

        self::assertSame($expectedAmount, $basketAmount->getAmount());
        self::assertSame($expectedCurrency, $basketAmount->getCurrency());
    }
}