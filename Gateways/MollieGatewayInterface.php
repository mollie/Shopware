<?php

namespace MollieShopware\Gateways;


use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Issuer;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Shipment;

interface MollieGatewayInterface
{

    /**
     * @param MollieApiClient $client
     */
    public function switchClient(MollieApiClient $client);

    /**
     * @return string
     */
    public function getOrganizationId();

    /**
     * @return string
     */
    public function getProfileId();

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

    /**
     * @param Order $mollieOrder
     * @param string $carrier
     * @param string $trackingNumber
     * @param string $trackingUrl
     * @return Shipment
     */
    public function shipOrder(Order $mollieOrder, $carrier, $trackingNumber, $trackingUrl);

}
