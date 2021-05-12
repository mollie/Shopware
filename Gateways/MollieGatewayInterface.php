<?php

namespace MollieShopware\Gateways;


use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Issuer;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;

interface MollieGatewayInterface
{

    /**
     * @param MollieApiClient $client
     */
    public function switchClient(MollieApiClient $client);

    /**
     * @param $orderId
     * @return Order
     */
    public function getOrder($orderId);

    /**
     * @param $paymentId
     * @return Payment
     */
    public function getPayment($paymentId);

    /**
     * @return Issuer[]
     */
    public function getIdealIssuers();

}
