<?php

namespace MollieShopware\Models;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Logger;
use MollieShopware\Exceptions\MolliePaymentConfigurationNotFound;
use MollieShopware\Models\Payment\Configuration;
use MollieShopware\MollieShopware;
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
     * @param string $mollieID
     * @return Transaction|null
     */
    public function getTransactionByMollieIdentifier($mollieID)
    {
        $query = $this->getEntityManager()->createQueryBuilder();

        $query->select(['t'])
            ->from(Transaction::class, 't')
            ->where(
                $query->expr()->orX(
                    $query->expr()->eq('t.mollieId', ':mollieId'),
                    $query->expr()->eq('t.molliePaymentId', ':mollieId')
                )
            )
            ->setParameter(':mollieId', $mollieID);

        /** @var array $result */
        $result = $query->getQuery()->getResult();

        if (count($result) >= 1) {
            return $result[0];
        }

        return null;
    }

    /**
     * This function gets all orders Klarna PAY NOW or PAY LATER orders
     * that have not yet been shipped.
     *
     * @return Transaction[]
     */
    public function getShippableKlarnaTransactions()
    {
        $query = $this->getEntityManager()->createQueryBuilder();

        $query
            ->select(['t'])
            ->from(Transaction::class, 't')
            ->where(
                $query->expr()->eq('t.isShipped', ':isShipped')
            )
            ->andWhere(
                $query->expr()->orX(
                    $query->expr()->eq('t.paymentMethod', ':payLater'),
                    $query->expr()->eq('t.paymentMethod', ':payNow')
                )
            )
            ->setParameter(':isShipped', false)
            ->setParameter(':payLater', MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_LATER)
            ->setParameter(':payNow', MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_NOW);

        /** @var Transaction[] $result */
        $result = $query->getQuery()->getResult();

        if ($result === null || !is_array($result)) {
            return [];
        }

        return $result;
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
