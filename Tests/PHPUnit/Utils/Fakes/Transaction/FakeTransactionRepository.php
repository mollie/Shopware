<?php

namespace MollieShopware\Tests\Utils\Fakes\Transaction;


use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepositoryInterface;


class FakeTransactionRepository implements TransactionRepositoryInterface
{

    /**
     * @return int
     */
    public function getLastId()
    {
        return 5;
    }

    public function save(Transaction $transaction)
    {

    }


}
