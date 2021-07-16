<?php

namespace MollieShopware\Facades\Shipping;


use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Gateways\Mollie\MollieGatewayFactory;
use Psr\Log\InvalidArgumentException;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

class ShipOrderFacade
{

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var MollieGatewayFactory
     */
    private $mollieGatewayFactory;

    /**
     * @var \Smarty
     */
    private $template;


    /**
     * ShipOrderFacade constructor.
     * @param OrderService $orderService
     * @param MollieGatewayFactory $mollieGatewayFactory
     * @param \Smarty $template
     */
    public function __construct(OrderService $orderService, MollieGatewayFactory $mollieGatewayFactory, \Smarty $template)
    {
        $this->orderService = $orderService;
        $this->mollieGatewayFactory = $mollieGatewayFactory;
        $this->template = $template;
    }


    /**
     * @param string $orderNumber
     * @param string|null $articleNumber
     * @param int|null $shipQuantity
     * @throws OrderNotFoundException
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function shipOrder($orderNumber, $articleNumber, $shipQuantity)
    {
        if ($orderNumber === null) {
            throw new InvalidArgumentException('Missing Order Number!');
        }

        $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

        if (!$order instanceof Order) {
            throw new OrderNotFoundException('Order with number: ' . $orderNumber . ' has not been found in Shopware!');
        }


        # create our mollie gateway with the configuration from the shop of our order
        $mollieGateway = $this->mollieGatewayFactory->createForShop($order->getShop()->getId());

        # now retrieve the mollie order object from our gateway
        $mollieId = $this->orderService->getMollieOrderId($order);
        $mollieOrder = $mollieGateway->getOrder($mollieId);


        $shipping = new MollieShipping($mollieGateway, $this->template);

        # now either perform a full shipment
        # or only a partial shipment for our order and its articles
        $isPartial = ($articleNumber !== null);

        if (!$isPartial) {

            $shipping->shipOrder($order, $mollieOrder);

        } else {

            $detail = $this->searchArticleItem($order, $articleNumber);

            # if we did not provide a quantity
            # then we use the full quantity that has been ordered
            if ($shipQuantity === null) {
                $shipQuantity = $detail->getQuantity();
            }

            $shipping->shipOrderPartially(
                $order,
                $mollieOrder,
                $detail->getId(),
                $shipQuantity
            );
        }
    }

    /**
     * @param Order $order
     * @param string $articleNumber
     * @return Detail
     * @throws \Exception
     */
    private function searchArticleItem(Order $order, $articleNumber)
    {
        /** @var Detail $detail */
        foreach ($order->getDetails() as $detail) {

            if ($detail->getArticleNumber() === $articleNumber) {
                return $detail;
            }
        }

        throw new \Exception('Line Item with article number: ' . $articleNumber . ' not found in this order!');
    }

}
