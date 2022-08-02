<?php

namespace MollieShopware\Models\Payment;

use MollieShopware\Exceptions\MolliePaymentConfigurationNotFound;
use Shopware\Components\Model\ModelRepository;

class Repository extends ModelRepository
{

    /**
     * @param Configuration $configuration
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(Configuration $configuration)
    {
        $this->getEntityManager()->persist($configuration);
        $this->getEntityManager()->flush($configuration);
    }

    /**
     * @param $paymentMeanId
     * @throws MolliePaymentConfigurationNotFound
     * @return Configuration
     */
    public function getByPaymentId($paymentMeanId)
    {
        $config = $this->findOneBy([
            'paymentMeanId' => $paymentMeanId
        ]);

        if (!$config instanceof Configuration) {
            throw new MolliePaymentConfigurationNotFound('No payment configuration found for ID: ' . $paymentMeanId);
        }

        return $config;
    }

    /**
     * @param $paymentName
     * @throws MolliePaymentConfigurationNotFound
     * @return Configuration
     */
    public function getByPaymentName($paymentName)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select(['c'])
            ->from(Configuration::class, 'c')
            ->leftJoin('c.payment', 'p', 's_core_paymentmeans')
            ->where($qb->expr()->eq('p.name', ':name'))
            ->setParameter('name', $paymentName);

        /** @var array $result */
        $result = $qb->getQuery()->getResult();

        if (count($result) <= 0) {
            throw new MolliePaymentConfigurationNotFound('No payment configuration found for: ' . $paymentName);
        }

        $result = $result[0];

        if (!$result instanceof Configuration) {
            throw new MolliePaymentConfigurationNotFound('No payment configuration found for: ' . $paymentName);
        }

        return $result;
    }

    /**
     *
     */
    public function cleanLegacyData()
    {
        $connection = $this->getEntityManager()->getConnection();

        $sql = 'DELETE c 
                FROM mol_sw_paymentmeans c
                LEFT JOIN s_core_paymentmeans p ON c.paymentmean_id = p.id 
                where p.id IS NULL';

        $connection->executeQuery($sql);
    }
}
