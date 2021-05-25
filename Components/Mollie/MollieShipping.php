<?php

namespace MollieShopware\Components\Mollie;

use MollieShopware\Gateways\MollieGatewayInterface;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Order;

class MollieShipping
{

    /**
     * Shopware tracking code variable of order.
     */
    const TRACKING_CODE_VARIABLE = '{$sOrder.trackingcode}';

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;


    /**
     * @param MollieGatewayInterface $gwMollie
     */
    public function __construct(MollieGatewayInterface $gwMollie)
    {
        $this->gwMollie = $gwMollie;
    }

    /**
     * @param Order $order
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrder(Order $order, \Mollie\Api\Resources\Order $mollieOrder)
    {
        $shippingCarrier = (string)($order->getDispatch() instanceof Dispatch) ? $order->getDispatch()->getName() : '-';
        $trackingUrl = (string)($order->getDispatch() instanceof Dispatch) ? $order->getDispatch()->getStatusLink() : '';
        $trackingCode = (string)$order->getTrackingCode();

        # replace the tracking code variable in our tracking URL
        if (!empty($trackingUrl)) {
            $trackingUrl = str_replace(self::TRACKING_CODE_VARIABLE, $trackingCode, $trackingUrl);
        }

        return $this->gwMollie->shipOrder(
            $mollieOrder,
            $shippingCarrier,
            $trackingCode,
            $trackingUrl
        );
    }

}
