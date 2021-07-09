<?php

namespace MollieShopware\Components\Translation;


use Doctrine\DBAL\Connection;

class Translation
{

    const PAYMENT_KEY = 'config_payment';

    /**
     * @var Connection
     */
    private $connection;


    /**
     * @param Connection $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param $configKey
     * @param $paymentId
     * @param $shopId
     * @return string
     * @throws \Doctrine\DBAL\Exception
     */
    public function getPaymentConfigTranslation($configKey, $paymentId, $shopId)
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')
            ->from('s_core_translations')
            ->where($qb->expr()->eq('objecttype', ':objecttype'))
            ->andWhere($qb->expr()->eq('objectlanguage', ':objectlanguage'))
            ->setParameter('objecttype', self::PAYMENT_KEY)
            ->setParameter('objectlanguage', $shopId);

        $row = $qb->execute()->fetch();

        $data = unserialize($row['objectdata']);

        if (!is_array($data)) {
            return '';
        }

        if (!array_key_exists($paymentId, $data)) {
            return '';
        }

        $paymentConfig = $data[$paymentId];

        if (!array_key_exists($configKey, $paymentConfig)) {
            return '';
        }

        $paymentValue = $paymentConfig[$configKey];

        return (string)$paymentValue;
    }

}
