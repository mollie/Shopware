<?php

namespace MollieShopware\Models;

use MollieShopware\Components\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelRepository;
use Shopware\Models\Order\Order;

class TransactionRepository extends ModelRepository implements TransactionRepositoryInterface
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
     * @param int $orderId
     * @return Transaction
     */
    public function getTransactionByOrder($orderId)
    {
        /** @var Transaction $transaction */
        $transaction = $this->findOneBy([
            'orderId' => $orderId
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

            if (!empty($transaction)) {
                $id = $transaction->getId();
            }
        } catch (\Exception $ex) {
            $this->getLogger()->error(
                'Error when loading last ID',
                [
                    'error' => $ex->getMessage(),
                ]
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
