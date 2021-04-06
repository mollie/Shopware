<?php

namespace MollieShopware\Models\SessionSnapshot;

use Doctrine\ORM\EntityManager;
use Shopware\Components\Model\ModelRepository;


class Repository extends ModelRepository
{

    /**
     * @param int $transactionId
     * @return SessionSnapshot
     */
    public function findByTransactionId($transactionId)
    {
        return $this->findOneBy([
            'transactionId' => $transactionId
        ]);
    }

    /**
     */
    public function save(SessionSnapshot $snapshot)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getEntityManager();

        $entityManager->persist($snapshot);
        $entityManager->flush();
    }

    /**
     */
    public function delete(SessionSnapshot $snapshot)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getEntityManager();

        $entityManager->remove($snapshot);
        $entityManager->flush();
    }

}
