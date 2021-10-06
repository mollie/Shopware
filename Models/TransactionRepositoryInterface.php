<?php

namespace MollieShopware\Models;


interface TransactionRepositoryInterface
{

    /**
     * @param string $mollieID
     * @return Transaction|null
     */
    public function getTransactionByMollieIdentifier($mollieID);

    /**
     * @return mixed
     */
    public function getLastId();

    /**
     * @param Transaction $transaction
     * @return mixed
     */
    public function save(Transaction $transaction);

}
