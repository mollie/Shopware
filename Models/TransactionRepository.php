<?php

namespace MollieShopware\Models;

use MollieShopware\Components\Logger;
use Psr\Log\LoggerInterface;
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
     * @return \MollieShopware\Models\Transaction
     * @throws \Exception
     *
     */
    public function create(
        \Shopware\Models\Order\Order $order = null,
        \Mollie\Api\Resources\Order $mollieOrder = null,
        \Mollie\Api\Resources\Payment $molliePayment = null
    )
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
     * @param Transaction $transaction
     * @return \MollieShopware\Models\Transaction
     * @throws \Exception
     */
    public function save(Transaction $transaction)
    {
        try {
            $this->getEntityManager()->persist($transaction);
            $this->getEntityManager()->flush($transaction);
        } catch (\Exception $ex) {

            $this->getLogger()->error(
                'Error when saving transaction in database',
                array(
                    'error' => $ex->getMessage(),
                )
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
    public function getMostRecentTransactionForOrder(\Shopware\Models\Order\Order $order)
    {
        /** @var Transaction $transaction */
        $transaction = $this->findOneBy([
            'orderId' => $order->getId()
        ]);

        return $transaction;
    }

    /**
     * Get the last transaction id from the database
     *
     * @return int|null
     * @throws \Exception
     */
    public function getLastId()
    {
        $id = null;

        try {
            /** @var Transaction $transaction */
            $transaction = $this->findOneBy([], ['id' => 'DESC']);

            if (!empty($transaction))
                $id = $transaction->getId();
        } catch (\Exception $ex) {

            $this->getLogger()->error(
                'Error when loading last ID',
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $id;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger()
    {
        return Shopware()->Container()->get('mollie_shopware.components.logger');
    }

}