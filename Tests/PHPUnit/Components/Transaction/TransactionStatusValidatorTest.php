<?php

namespace MollieShopware\Tests\Components\Transaction;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Transaction\TransactionStatusValidator;
use MollieShopware\Models\Transaction;
use PHPUnit\Framework\TestCase;


class TransactionStatusValidatorTest extends TestCase
{

    /**
     * This test verifies that our transaction always pending if
     * it has any valid payment, but not order ID.
     *
     * @covers \MollieShopware\Components\Transaction\TransactionStatusValidator::isTransactionPending
     */
    public function testPendingWithoutOrderID()
    {
        $validator = new TransactionStatusValidator();

        $transaction = new Transaction();
        $transaction->setOrderNumber('1');

        $isPending = $validator->isTransactionPending($transaction, PaymentStatus::MOLLIE_PAYMENT_PAID);

        $this->assertEquals(true, $isPending);
    }

    /**
     * This test verifies that our transaction always pending if
     * it has any valid payment, but not order number.
     *
     * @covers \MollieShopware\Components\Transaction\TransactionStatusValidator::isTransactionPending
     */
    public function testPendingWithoutOrderNumber()
    {
        $validator = new TransactionStatusValidator();

        $transaction = new Transaction();
        $transaction->setOrderId(1);

        $isPending = $validator->isTransactionPending($transaction, PaymentStatus::MOLLIE_PAYMENT_PAID);

        $this->assertEquals(true, $isPending);
    }

    /**
     * This test verifies that a transaction with a valid payment
     * and a linked order ID + Number is finished and thus not pending.
     *
     * @covers \MollieShopware\Components\Transaction\TransactionStatusValidator::isTransactionPending
     */
    public function testNotPendingWithOrder()
    {
        $validator = new TransactionStatusValidator();

        $transaction = new Transaction();
        $transaction->setOrderId(1);
        $transaction->setOrderNumber('1');

        $isPending = $validator->isTransactionPending($transaction, PaymentStatus::MOLLIE_PAYMENT_PAID);

        $this->assertEquals(false, $isPending);
    }

    /**
     * This test verifies that any invalid payment is NOT pending but finished and complete!
     * There won't ever come anything else, its just complete!
     * Even if we would have an order number or ID, it will be finished
     *
     * @covers       \MollieShopware\Components\Transaction\TransactionStatusValidator::isTransactionPending
     *
     * @dataProvider getFailedStates
     * @param $failedStatus
     */
    public function testNotPendingWithInvalidPayment($failedStatus)
    {
        $validator = new TransactionStatusValidator();

        $transaction = new Transaction();
        $transaction->setOrderId(1);
        $transaction->setOrderNumber('1');

        $isPending = $validator->isTransactionPending($transaction, $failedStatus);

        $this->assertEquals(false, $isPending);
    }

    /**
     * @return array[]
     */
    public function getFailedStates()
    {
        return array(
            PaymentStatus::MOLLIE_PAYMENT_EXPIRED => array(PaymentStatus::MOLLIE_PAYMENT_EXPIRED),
            PaymentStatus::MOLLIE_PAYMENT_CANCELED => array(PaymentStatus::MOLLIE_PAYMENT_CANCELED),
            PaymentStatus::MOLLIE_PAYMENT_FAILED => array(PaymentStatus::MOLLIE_PAYMENT_FAILED),
        );
    }

}
