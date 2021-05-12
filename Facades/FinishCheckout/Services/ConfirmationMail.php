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
        /**
         * Get the variables for the order mail from the transaction. The order mail variables
         * are returned as JSON value, we decode that JSON to an array here.
         *
         * @var array $variables
         */
        $variables = @json_decode($transaction->getOrdermailVariables(), true);

        /**
         * Send the confirmation e-mail using the retrieved variables
         * or log an error if the e-mail could not be sent.
         */
        if (is_array($variables)) {
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
}
