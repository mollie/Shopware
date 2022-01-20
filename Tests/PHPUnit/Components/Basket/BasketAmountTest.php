<?php

namespace MollieShopware\Tests\Components\Basket;

use MollieShopware\Components\Basket\BasketAmount;
use PHPUnit\Framework\TestCase;

class BasketAmountTest extends TestCase
{
    public function testCanAddAmountToBasketAmount()
    {
        $test = new \Enlight_Collection_ArrayCollection(['view' => '12345']);

        $expectedAmount = 15.0;

        $basketAmount = new BasketAmount();
        $basketAmount->setAmount($expectedAmount);

        self::assertSame($expectedAmount, $basketAmount->getAmount());
    }

    public function testCanAddCurrencyToBasketAmount()
    {
        $expectedCurrency = 'EUR';

        $basketAmount = new BasketAmount();
        $basketAmount->setCurrency($expectedCurrency);

        self::assertSame($expectedCurrency, $basketAmount->getCurrency());
    }

    public function testCanAddAmountAndCurrencyInConstructor()
    {
        $expectedAmount = 25.5;
        $expectedCurrency = 'USD';

        $basketAmount = new BasketAmount($expectedAmount, $expectedCurrency);

        self::assertSame($expectedAmount, $basketAmount->getAmount());
        self::assertSame($expectedCurrency, $basketAmount->getCurrency());
    }
}