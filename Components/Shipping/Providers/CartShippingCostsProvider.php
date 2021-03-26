<?php

namespace MollieShopware\Components\Shipping\Providers;

use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Shipping\Models\ShippingCosts;
use MollieShopware\Components\Shipping\ShippingCostsProviderInterface;
use MollieShopware\Exceptions\OrderNotFoundBySessionIdException;
use Shopware\Models\Order\Order;

class CartShippingCostsProvider implements ShippingCostsProviderInterface
{
    /** @var OrderService */
    private $orderService;

    /**
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * @return ShippingCosts
     * @throws OrderNotFoundBySessionIdException
     */
    public function getShippingCosts()
    {
        try {
            // Use the already in order calculated shipping costs if they exist
            $shippingCosts = $this->getInvoiceShippingCosts();
        } catch (OrderNotFoundBySessionIdException $ex) {
            // If order not found by session ID use backend shipping costs configuration.
            // This is an important Fallback for Direct Payment Methods which don't create
            // orders in database before they redirect to the external direct checkout
            $shippingCosts = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts();
        }

        $unitPrice = 0;
        $unitPriceNet = 0;
        $taxRate = 0;

        if (is_array($shippingCosts) && count($shippingCosts)) {
            $taxRate = floatval($shippingCosts['tax']);
            $unitPrice = floatval($shippingCosts['brutto']);
            $unitPriceNet = floatval($shippingCosts['netto']);
        }

        return new ShippingCosts(
            $unitPrice,
            $unitPriceNet,
            $taxRate
        );
    }

    /**
     * @return array|null
     * @throws OrderNotFoundBySessionIdException
     */
    private function getInvoiceShippingCosts()
    {
        $sessionId = Shopware()->Session()->offsetGet('sessionId');

        /** @var Order $order */
        $order = $this->orderService->getOrderBySessionId($sessionId);

        if ($order === null) {
            throw new OrderNotFoundBySessionIdException($sessionId);
        }

        $brutto = round($order->getInvoiceShipping(), 2);
        $netto = round($order->getInvoiceShippingNet(), 2);
        $taxRate = $this->getTaxRate($order);

        return [
            'brutto' => $brutto,
            'netto' => $netto,
            'tax' => $taxRate
        ];
    }

    /**
     * @param Order $order
     * @return float
     */
    private function getTaxRate(Order $order)
    {
        $taxRate = null;

        // This Method doesn't exists in early Shopware 5 Versions (<= 5.4)
        // if the Method exists use it, if not calculate the TaxRate as fallback
        if (method_exists($order, 'getInvoiceShippingTaxRate')) {
            $taxRate = $order->getInvoiceShippingTaxRate();
        }

        if ($taxRate !== null) {
            return $taxRate;
        }

        if ($order->getInvoiceShipping() === $order->getInvoiceShippingNet()) {
            return 0.0;
        }

        // taxRate = (unroundedBrutto / unroundedNetto - 1) * 100, eg. (1.19 / 1 - 1) * 100 = 19.0
        return (round($order->getInvoiceShipping() / $order->getInvoiceShippingNet(), 2) - 1) * 100;
    }
}
