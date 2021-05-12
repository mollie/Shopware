<?php

namespace MollieShopware\Models;


interface TransactionRepositoryInterface
{

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
