<?php

	// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Models;

use Shopware\Components\Model\ModelRepository;
use MollieShopware\Models\Transaction;
use MollieShopware\Components\Constants\PaymentStatus;
use Exception;
use DateTime;

class OrderDetailMollieIDRepository extends ModelRepository
{

    public function Save(OrderDetailMollieID $orderDetailMollieID)
    {

        $entityManager = $this->getEntityManager();
        $entityManager->persist($orderDetailMollieID);
        $entityManager->flush();

    }

}
