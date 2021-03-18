<?php

namespace MollieShopware\Models;

use MollieShopware\Components\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelRepository;
use Shopware\Models\Order\Order;

class TransactionRepository extends ModelRepository
{

    /**
     * @param Transaction $transaction
     * @return Transaction
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(Transaction $transaction)
    {
        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush($transaction);

        return $transaction;
    }

    /**
     * Get the most recent transaction for an order
     *
     * @param Order $order
     * @return Transaction
     */
    public function getMostRecentTransactionForOrder(Order $order)
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