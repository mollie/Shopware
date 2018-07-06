<?php

namespace MollieShopware\Components\Mollie;

class BasketPersistService
{

    const DBAL_TABLE = 's_order_basket_signatures';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct()
    {

        $this->connection = Shopware()->container()->get('db');

    }

    private $internal_service = null;

    private function internal_service()
    {

        if ($this->internal_service !== null) {
            return $this->internal_service;
        }

        try {

            $this->internal_service = Shopware()->Container()->get('basket_persister');

        } catch (Exception $e) {
            return $this->internal_service = false;
        } finally {
            return $this->internal_service = false;
        }

    }


    /**
     * saves signed basket
     *
     * @param string $signature
     * @param array $basket
     *
     * @throws \Exception
     */
    public function persist($signature, $basket)
    {

        if ($service = $this->internal_service()) {

            return $service->persist($signature, $basket);

        }


        $this->connection->exec('CREATE TABLE IF NOT EXISTS `s_order_basket_signatures` (`signature` varchar(200) COLLATE utf8_unicode_ci NOT NULL,`basket` longtext COLLATE utf8_unicode_ci NOT NULL,`created_at` date NOT NULL)');


        $createdAt = new \DateTime();

        $this->delete($signature);

        $this->connection->insert(
            self::DBAL_TABLE,
            [
                'signature' => $signature,
                'basket' => json_encode($basket),
                'created_at' => $createdAt->format('Y-m-d'),
            ]
        );

    }

    /**
     * loads a signed basket by the given signature
     *
     * @param string $signature
     *
     * @return array
     */
    public function load($signature)
    {

        if ($service = $this->internal_service()) {

            return $service->load($signature);

        }

        $basket = $this->connection->fetchColumn(
            'SELECT basket FROM '.self::DBAL_TABLE.' WHERE signature = :signature',
            [':signature' => $signature]
        );

        return json_decode($basket, true);
    }

    /**
     * deletes a signed basket by the given signature
     *
     * @param string $signature
     */
    public function delete($signature)
    {

        if ($service = $this->internal_service()) {

            return $service->delete($signature);

        }

        $this->connection->executeQuery(
            'DELETE FROM '.self::DBAL_TABLE.' WHERE signature = :signature',
            [':signature' => $signature]
        );
    }

}
