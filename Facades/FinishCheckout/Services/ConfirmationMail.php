<?php

namespace MollieShopware\Facades\FinishCheckout\Services;

use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;

class ConfirmationMail
{

    /**
     * @var $sOder
     */
    private $sOder;

    /**
     * @var TransactionRepository
     */
    private $repoTransaction;


    /**
     * ConfirmationMail constructor.
     * @param $sOder
     * @param TransactionRepository $repoTransaction
     */
    public function __construct($sOder, TransactionRepository $repoTransaction)
    {
        $this->sOder = $sOder;
        $this->repoTransaction = $repoTransaction;
    }


    /**
     * @param Transaction $transaction
     * @throws \Exception
     */
    public function sendConfirmationEmail(Transaction $transaction)
    {
        $variables = @json_decode($transaction->getOrdermailVariables(), true);

        if (!is_array($variables)) {

            $errorCode = json_last_error();

            throw new \Exception('Required OrderMailVariables are NULL. Confirmation mail cannot be sent without data! JSON Decode Error Code: ' . $errorCode);
        }


        $this->sOder->sUserData = $variables;

        if (!is_array($this->sOder->sBasketData)) {
            $this->sOder->sBasketData = [];
        }
        $this->sOder->sBasketData['sCurrencyName'] = $transaction->getCurrency();

        if (isset($variables['additional']['charge_vat']) && $variables['additional']['charge_vat'] === false) {
            $this->sOder->sNet = true;
        }

        $this->sOder->sendMail($variables);
    }

}
