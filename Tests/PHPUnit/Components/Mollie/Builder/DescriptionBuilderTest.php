<?php

namespace MollieShopware\Tests\Components\Mollie\Builder;

use MollieShopware\Components\Mollie\Builder\Payment\DescriptionBuilder;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;


class DescriptionBuilderTest extends TestCase
{

    /**
     * @var DescriptionBuilder
     */
    private $builder;


    /**
     *
     */
    public function setUp(): void
    {
        $this->builder = new DescriptionBuilder();
    }


    /**
     * This test verifies that a transaction without an existing
     * order number gets a correct UUID as description with a
     * prefixed "Transaction".
     */
    public function testDescriptionNoOrder()
    {
        $transaction = new Transaction();
        $transaction->setOrderNumber('');

        $description = $this->builder->buildDescription($transaction, 'uuid-123');

        $this->assertEquals('Transaction uuid-123', $description);
    }

    /**
     * This test verifies that a transaction that has a order number
     * gets a correct UUID as description as well as "Order" as prefix text.
     */
    public function testDescriptionWithOrder()
    {
        $transaction = new Transaction();
        $transaction->setOrderNumber('20001');

        $description = $this->builder->buildDescription($transaction, 'uuid-123');

        $this->assertEquals('Order 20001', $description);
    }

}
