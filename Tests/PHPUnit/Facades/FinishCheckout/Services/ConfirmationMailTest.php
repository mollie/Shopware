<?php

namespace MollieShopware\Tests\Facades\FinishCheckout\Services;

use Exception;
use MollieShopware\Facades\FinishCheckout\Services\ConfirmationMail;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use PHPUnit\Framework\TestCase;
use sOrder;

class ConfirmationMailTest extends TestCase
{
    /**
     * @var ConfirmationMail $confirmationMail
     */
    private $confirmationMail;

    /**
     * @var sOrder $sOrder
     */
    private $sOrder;

    public function setUp(): void
    {
        $this->sOrder = $this->createMock(sOrder::class);
        $transactionRepository = $this->createMock(TransactionRepository::class);
        $this->confirmationMail = new ConfirmationMail($this->sOrder, $transactionRepository);
    }

    /**
     * @test
     * @testdox Method sendConfirmationEmail() sends only one mail for a transaction.
     *
     * @return void
     * @throws Exception
     */
    public function testSendConfirmationEmailSendsOnlyOneMailForTransaction()
    {
        $transaction = new Transaction();

        $transaction->setOrdermailVariables('{ "sOrderDetails": [] }');
        $transaction->setConfirmationMailSent(false);

        $this->sOrder->expects(self::once())->method('sendMail');

        # after the first run of method sendConfirmationEmail, the confirmation
        # mail sent flag is set, a second trigger will throw an exception
        self::expectExceptionMessage('The confirmation e-mail is already sent.');

        # we trigger the sendConfirmationEmail method more than once on the same
        # transaction, in the first run the confirmation mail sent flag should
        # be added, so the next runs will throw an exception and not trigger
        # the sendMail method on sOrder
        $this->confirmationMail->sendConfirmationEmail($transaction);
        $this->confirmationMail->sendConfirmationEmail($transaction);
        $this->confirmationMail->sendConfirmationEmail($transaction);
    }
}
