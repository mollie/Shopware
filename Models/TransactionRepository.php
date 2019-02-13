<?php

// Mollie Shopware Plugin Version: 1.4

namespace MollieShopware\Models;

use MollieShopware\Components\Logger;
use Shopware\Components\Model\ModelRepository;

class TransactionRepository extends ModelRepository
{
    /**
     * Create a new transaction for the given order with the given
     * mollie Order object. This stores the mollie ID with the
     * order so it can be recovered later.
     *
     * @param \Shopware\Models\Order\Order|null $order
     * @param \Mollie\Api\Resources\Order|null $mollieOrder
     * @param \Mollie\Api\Resources\Payment|null $molliePayment
     *
     * @throws \Exception
     *
     * @return \MollieShopware\Models\Transaction
     */
    public function create($order = null, $mollieOrder = null, $molliePayment = null)
    {
        $transaction = new Transaction();
        $transactionId = $this->getLastId() + 1;

        if (!empty($transaction)) {
            $transaction->setId($transactionId);
            $transaction->setTransactionId('mollie_' . $transactionId);
            $transaction->setSessionId(\Enlight_Components_Session::getId());

            if (!empty($order))
                $transaction->setOrderId($order->getId());

            if (!empty($mollieOrder))
                $transaction->setMollieId($mollieOrder->id);

            if (!empty($molliePayment))
                $transaction->setMolliePaymentId($molliePayment->id);

            $this->save($transaction);
        }

        return $transaction;
    }

    /**
     * Save a transaction to the database
     *
     * @param \MollieShopware\Models\Transaction $transaction
     * @return \MollieShopware\Models\Transaction
     * @throws \Exception
     */
    public function save($transaction)
    {
        try {
            $this->getEntityManager()->persist($transaction);
            $this->getEntityManager()->flush();
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $transaction;
    }

    /**
     * Get the most recent transaction for an order
     *
     * @param \Shopware\Models\Order\Order $order
     * @return Transaction
     */
    public function getMostRecentTransactionForOrder($order)
    {
        /** @var Transaction $transaction */
        $transaction = $this->findOneBy([
            'orderId'=> $order->getId()
        ]);

        return $transaction;
    }

    /**
     * Get the last transaction id from the database
     *
     * @throws \Exception
     * @return int|null
     */
    public function getLastId()
    {
        $id = null;

        try {
            /** @var Transaction $transaction */
            $transaction = $this->findOneBy([], ['id' => 'DESC']);

            if (!empty($transaction))
                $id = $transaction->getId();
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $id;
    }
}