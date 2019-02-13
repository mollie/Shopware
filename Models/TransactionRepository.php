<?php

// Mollie Shopware Plugin Version: 1.4

namespace MollieShopware\Models;

use Doctrine\ORM\QueryBuilder;
use MollieShopware\Components\Logger;
use Shopware\Components\Model\ModelRepository;
use MollieShopware\Components\Constants\PaymentStatus;
use Shopware\Models\Order\Order;
use Enlight_Components_Session;

class TransactionRepository extends ModelRepository
{
    /**
     * Create a new transaction for the given order with the given
     * mollie Order object. This stores the mollie ID with the
     * order so it can be recovered later.
     * @param Order|null $order
     * @param \Mollie\Api\Resources\Order|null $mollieOrder
     * @return \MollieShopware\Models\Transaction
     */
    public function create(Order $order = null, \Mollie\Api\Resources\Order $mollieOrder = null, $molliePayment = null)
    {
        // get new transaction ID
        $transactionId = $this->getLastId() + 1;

        // create the transaction
        $transaction = new Transaction();
        $transaction->setId($transactionId);
        $transaction->setTransactionId('mollie_' . $transactionId);
        $transaction->setSessionId(Enlight_Components_Session::getId());

        // add the order ID if present
        if ($order) {
            $transaction->setOrderId($order->getId());
        }

        // add the mollie order ID if present
        if ($transaction) {
            $transaction->setMollieId($mollieOrder->id);
        }

        // add the mollie payment ID if present
        if ($molliePayment) {
            $transaction->setMolliePaymentId($molliePayment->id);
        }

        // save the transaction
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
        try {
            $this->getEntityManager()->persist($transaction);
            $this->getEntityManager()->flush();
        }
        catch (Exception $ex) {
            // @todo Handle exception
        }

        return $transaction;
    }

    /**
     * @param \Shopware\Models\Order\Order $order
     * @return Transaction
     */
    public function getMostRecentTransactionForOrder($order)
    {
        return $this->findOneBy([
            'orderId'=> $order->getId()
        ]);
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
                $id = $result->getId();
        }
        catch (\Exception $ex) {
            // write exception to log
            Logger::log('error', $ex->getMessage(), $ex);
        }

        return $id;
    }
}