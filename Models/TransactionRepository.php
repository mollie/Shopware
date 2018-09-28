<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Models;

use Shopware\Components\Model\ModelRepository;
use MollieShopware\Models\Transaction;
use MollieShopware\Components\Constants\PaymentStatus;
use Exception;
use DateTime;
use Shopware\Models\Order\Order;

class TransactionRepository extends ModelRepository
{

    /**
     * Create a new transaction for the given order with the given
     * mollie Order object. This stores the mollie ID with the
     * order so it can be recovered later.
     *
     * @param Order $order
     * @param \Mollie\Api\Resources\Order $mollie_order
     */
    public function create(Order $order, \Mollie\Api\Resources\Order $mollie_order)
    {

        $transaction = new Transaction();

        $transaction->setOrderID($order->getId());
        $transaction->setMollieID($mollie_order->id);

        $this->save($transaction);

    }

    /**
     * Saves a transaction to database
     *
     * @param \MollieShopware\Models\Transaction $transaction
     * @return \MollieShopware\Models\Transaction
     */
    public function save(Transaction $transaction)
    {

        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush();

        return $transaction;

    }

}
