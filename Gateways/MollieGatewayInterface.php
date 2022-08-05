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
     * @param array $requestData
     * @return Order
     */
    public function createOrder(array $requestData);

    /**
     * @param $mollieId
     * @param $orderNumber
     * @return mixed
     */
    public function updateOrderNumber($mollieId, $orderNumber);

    /**
     * @param $paymentId
     * @param $description
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return void
     */
    public function updatePaymentDescription($paymentId, $description);

    /**
     * @param Order $mollieOrder
     * @param string $carrier
     * @param string $trackingNumber
     * @param string $trackingUrl
     * @return Shipment
     */
    public function shipOrder(Order $mollieOrder, $carrier, $trackingNumber, $trackingUrl);

    /**
     * @param Order $mollieOrder
     * @param string $lineId
     * @param int $quantity
     * @param string $carrier
     * @param string $trackingNumber
     * @param string $trackingUrl
     * @return Shipment
     */
    public function shipOrderPartially(Order $mollieOrder, $lineId, $quantity, $carrier, $trackingNumber, $trackingUrl);

    /**
     * @param array $requestData
     * @return Payment
     */
    public function createPayment(array $requestData);
}
