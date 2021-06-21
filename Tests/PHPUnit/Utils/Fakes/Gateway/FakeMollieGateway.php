<?php

namespace MollieShopware\Tests\Utils\Fakes\Gateway;


use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Issuer;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Shipment;
use MollieShopware\Gateways\MollieGatewayInterface;

class FakeMollieGateway implements MollieGatewayInterface
{

    /**
     * @var Order
     */
    private $shippedOrder;

    /**
     * @var string
     */
    private $shippedCarrier;

    /**
     * @var string
     */
    private $shippedTrackingNumber;

    /**
     * @var string
     */
    private $shippedTrackingUrl;


    /**
     * @return Order
     */
    public function getShippedOrder(): Order
    {
        return $this->shippedOrder;
    }

    /**
     * @return string
     */
    public function getShippedCarrier(): string
    {
        return $this->shippedCarrier;
    }

    /**
     * @return string
     */
    public function getShippedTrackingNumber(): string
    {
        return $this->shippedTrackingNumber;
    }

    /**
     * @return string
     */
    public function getShippedTrackingUrl(): string
    {
        return $this->shippedTrackingUrl;
    }


    public function switchClient(MollieApiClient $client)
    {
    }

    public function getOrganizationId()
    {
    }

    public function getProfileId()
    {
    }

    public function getOrder($orderId)
    {
    }

    public function getPayment($paymentId)
    {
    }

    public function getIdealIssuers()
    {
    }

    /**
     * @param Order $mollieOrder
     * @param string $carrier
     * @param string $trackingNumber
     * @param string $trackingUrl
     * @return Shipment|void
     */
    public function shipOrder(Order $mollieOrder, $carrier, $trackingNumber, $trackingUrl)
    {
        $this->shippedOrder = $mollieOrder;
        $this->shippedCarrier = $carrier;
        $this->shippedTrackingNumber = $trackingNumber;
        $this->shippedTrackingUrl = $trackingUrl;
    }

}
