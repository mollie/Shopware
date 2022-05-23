<?php

namespace MollieShopware\Facades\FinishCheckout\Services;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;

class ConfirmationMail
{
    /**
     * @var $sOrder
     */
    private $sOrder;

    /**
     * @var TransactionRepository
     */
    private $repoTransaction;

    /**
     * Creates a new instance of the confirmation mail service.
     *
     * @param $sOrder
     * @param TransactionRepository $repoTransaction
     */
    public function __construct($sOrder, TransactionRepository $repoTransaction)
    {
        $this->sOrder = $sOrder;
        $this->repoTransaction = $repoTransaction;
    }

    /**
     * Sends a confirmation email for the provided transaction object.
     *
     * @param Transaction $transaction
     *
     * @throws \Exception
     */
    public function sendConfirmationEmail(Transaction $transaction)
    {
        if ($transaction->getConfirmationMailSent()) {
            throw new \Exception('The confirmation e-mail is already sent.');
        }

        $variables = @json_decode($transaction->getOrdermailVariables(), true);

        if (!is_array($variables)) {

            $errorCode = json_last_error();

            throw new \Exception('Required OrderMailVariables are NULL. Confirmation mail cannot be sent without data! JSON Decode Error Code: ' . $errorCode);
        }

        $this->sOrder->sUserData = $variables;

        if (!is_array($this->sOrder->sBasketData)) {
            $this->sOrder->sBasketData = [];
        }

        $this->sOrder->sBasketData['sCurrencyName'] = $transaction->getCurrency();

        if (isset($variables['additional']['charge_vat']) && $variables['additional']['charge_vat'] === false) {
            $this->sOrder->sNet = true;
        }

        $this->sOrder->sendMail($variables);

        $this->setConfirmationMailSentFlag($transaction);
    }

    /**
     * Stores a confirmation mail sent flag
     * on the provided transaction entity.
     *
     * @param Transaction $transaction
     *
     * @return void
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function setConfirmationMailSentFlag(Transaction $transaction)
    {
        $transaction->setConfirmationMailSent(true);

        $this->repoTransaction->save($transaction);
    }
}
