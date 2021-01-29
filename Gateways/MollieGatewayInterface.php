<?php

namespace MollieShopware\Gateways;

use Mollie\Api\MollieApiClient;

interface MollieGatewayInterface
{

    /**
     * @param $orderId
     * @return mixed
     */
    public function getOrder($orderId);

    /**
     * @param $paymentId
     * @return mixed
     */
    public function getPayment($paymentId);

}
