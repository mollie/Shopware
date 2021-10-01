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
     * @var string
     */
    private $shippedLineItemId;

    /**
     * @var int
     */
    private $shippedLineItemQuantity;


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

    /**
     * @return string
     */
    public function getShippedLineItemId(): string
    {
        return $this->shippedLineItemId;
    }

    /**
     * @return int
     */
    public function getShippedLineItemQuantity(): int
    {
        return $this->shippedLineItemQuantity;
    }

    /**
     * @param MollieApiClient $client
     */
    public function switchClient(MollieApiClient $client): void
    {
    }

    /**
     * @return string
     */
    public function getOrganizationId(): string
    {
        return 'org_test';
    }

    /**
     * @return string
     */
    public function getProfileId(): string
    {
        return 'prof_test';
    }

    /**
     * @param $orderId
     * @return Order
     */
    public function getOrder($orderId): Order
    {
        return new Order(null);
    }

    /**
     * @param $paymentId
     * @return Payment
     */
    public function getPayment($paymentId): Payment
    {
        return new Payment(null);
    }

    /**
     * @return array
     */
    public function getIdealIssuers(): array
    {
        return [];
    }

    /**
     * @param $mollieId
     * @param $orderNumber
     * @return mixed|void
     */
    public function updateOrderNumber($mollieId, $orderNumber)
    {
    }

    /**
     * @param $paymentId
     * @param $description
     */
    public function updatePaymentDescription($paymentId, $description)
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

    /**
     * @param Order $mollieOrder
     * @param string $lineId
     * @param int $quantity
     * @param string $carrier
     * @param string $trackingNumber
     * @param string $trackingUrl
     * @return Shipment|void
     */
    public function shipOrderPartially(Order $mollieOrder, $lineId, $quantity, $carrier, $trackingNumber, $trackingUrl)
    {
        $this->shippedOrder = $mollieOrder;
        $this->shippedLineItemId = $lineId;
        $this->shippedLineItemQuantity = $quantity;

        $this->shippedCarrier = $carrier;
        $this->shippedTrackingNumber = $trackingNumber;
        $this->shippedTrackingUrl = $trackingUrl;
    }

}
