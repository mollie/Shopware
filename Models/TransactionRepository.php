<?php

	// Mollie Shopware Plugin Version: 1.3.10.1

namespace MollieShopware\Models;

use Doctrine\ORM\QueryBuilder;
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
     * @param Order|null $order
     * @param \Mollie\Api\Resources\Order|null $mollie_order
     * @return \MollieShopware\Models\Transaction
     */
    public function create(Order $order = null, \Mollie\Api\Resources\Order $mollie_order = null)
    {

        $transaction = new Transaction();

        $transactionId = $this->getLastId() + 1;

        $transaction->setID($transactionId);
        $transaction->setTransactionID('mollie_' . $transactionId);

        if ($order){
            $transaction->setOrderID($order->getId());
        }
        if ($transaction){
            $transaction->setMollieID($mollie_order->id);
        }

        $this->save($transaction);

        return $transaction;

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

    /**
     * @param Order $order
     * @return Transaction
     */
    public function getMostRecentTransactionForOrder(Order $order)
    {

        return $this->findOneBy(['order_id'=> $order->getId()]);

    }

    /**
     * Get the last transaction id
     *
     * @return int|null
     */
    public function getLastId()
    {
        $id = null;

        try {
            $result = $this->findOneBy([], ['id' => 'DESC']);

            if (!empty($result))
                $id = $result->getID();
        }
        catch (Exception $ex) {
            // @todo Handle exception
            file_put_contents(__DIR__ . '/errors.txt', $ex->getMessage() . "\n", FILE_APPEND);
        }

        return $id;
    }
}
