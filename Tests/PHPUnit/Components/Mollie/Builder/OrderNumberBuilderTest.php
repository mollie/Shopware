<?php

namespace MollieShopware\Tests\Components\Mollie\Builder;

use MollieShopware\Components\Mollie\Builder\Payment\OrderNumberBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;


class OrderNumberBuilderTest extends TestCase
{

    /**
     * @var OrderNumberBuilder
     */
    private $builder;


    /**
     *
     */
    public function setUp(): void
    {
        $this->builder = new OrderNumberBuilder();
    }


    /**
     * This test verifies that the order number is filled correctly in requests of the Orders API.
     * If our transaction does not have an existing Shopware order number, we use as fallback value.
     */
    public function testOrderNumberNoOrder()
    {
        $transaction = new Transaction();
        $transaction->setOrderNumber('');
        $transaction->setId(2);
        $transaction->setBasketSignature('ABC');

        $orderNumber = $this->builder->buildOrderNumber($transaction, 'fallback');

        $this->assertEquals('fallback', $orderNumber);
    }

    /**
     * This test verifies that the order number is filled correctly in requests of the Orders API.
     * If our transaction has an existing Shopware order, we always use this
     * order number instead of the fallback value.
     */
    public function testOrderNumberWithOrder()
    {
        $transaction = new Transaction();
        $transaction->setOrderNumber('20004');
        $transaction->setId(2);
        $transaction->setBasketSignature('ABC');

        $orderNumber = $this->builder->buildOrderNumber($transaction, 'fallback');

        $this->assertEquals('20004', $orderNumber);
    }

}
