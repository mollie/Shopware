<?php

namespace MollieShopware\Components\Mollie;

use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Gateways\MollieGatewayInterface;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

class MollieShipping
{

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var \Smarty
     */
    private $smarty;

    /**
     * Mollie throws an error with length >= 100
     */
    const MAX_TRACKING_CODE_LENGTH = 99;


    /**
     * MollieShipping constructor.
     * @param MollieGatewayInterface $gwMollie
     * @param \Smarty $smarty
     */
    public function __construct(MollieGatewayInterface $gwMollie, \Smarty $smarty)
    {
        $this->gwMollie = $gwMollie;
        $this->smarty = $smarty;
    }

    /**
     * @param Order $shopwareOrder
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param $detailId
     * @param $quantity
     * @throws OrderNotFoundException
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrderPartially(Order $shopwareOrder, \Mollie\Api\Resources\Order $mollieOrder, $detailId, $quantity)
    {
        $foundItem = null;

        /** @var Detail $item */
        foreach ($shopwareOrder->getDetails() as $item) {
            if ($item->getId() === $detailId) {
                $foundItem = $item;
                break;
            }
        }

        if (!$foundItem instanceof Detail) {
            throw new OrderNotFoundException('Order Line Item with ID ' . $detailId . ' has not been found!');
        }


        $mollieLineItemId = $foundItem->getAttribute()->getMollieOrderLineId();

        $shippingCarrier = $this->getCarrier($shopwareOrder);
        $trackingCode = $this->getTrackingCode($shopwareOrder);
        $trackingUrl = $this->getTrackingUrl($shopwareOrder, $trackingCode);

        return $this->gwMollie->shipOrderPartially(
            $mollieOrder,
            $mollieLineItemId,
            $quantity,
            $shippingCarrier,
            $trackingCode,
            $trackingUrl
        );
    }

    /**
     * @param Order $order
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrder(Order $order, \Mollie\Api\Resources\Order $mollieOrder)
    {
        $shippingCarrier = $this->getCarrier($order);
        $trackingCode = $this->getTrackingCode($order);
        $trackingUrl = $this->getTrackingUrl($order, $trackingCode);

        return $this->gwMollie->shipOrder(
            $mollieOrder,
            $shippingCarrier,
            $trackingCode,
            $trackingUrl
        );
    }


    /**
     * @param Order $shopwareOrder
     * @return string
     */
    private function getCarrier(Order $shopwareOrder)
    {
        # unfortunately instanceOf is not enough
        # an empty dispatch that does not exist, return TRUE, but has the ID 0
        # so lets also ask for a valid ID > 0
        $hasDispatch = ($shopwareOrder->getDispatch() instanceof Dispatch) && ($shopwareOrder->getDispatch()->getId() > 0);

        return (string)($hasDispatch) ? trim($shopwareOrder->getDispatch()->getName()) : '-';
    }

    /**
     * @param Order $shopwareOrder
     * @return string
     */
    private function getTrackingCode(Order $shopwareOrder)
    {
        $code = trim($shopwareOrder->getTrackingCode());

        # Mollie does not allow codes >= 100
        # we just have to completely remove those codes, so that no tracking happens, but a shipping works.
        # still, if we find multiple codes (because separators exist), then we use the first one only
        if (strlen($code) > self::MAX_TRACKING_CODE_LENGTH) {
            if ($this->stringContains(',', $code)) {
                $code = trim(explode(',', $code)[0]);
            } elseif ($this->stringContains(';', $code)) {
                $code = trim(explode(';', $code)[0]);
            }

            # if we are still too long, then simply remove the code
            if (strlen($code) > self::MAX_TRACKING_CODE_LENGTH) {
                $code = '';
            }
        }

        return $code;
    }

    /**
     * @param Order $shopwareOrder
     * @param $trackingCode
     * @return array|string|string[]
     */
    private function getTrackingUrl(Order $shopwareOrder, $trackingCode)
    {
        # unfortunately instanceOf is not enough
        # an empty dispatch that does not exist, return TRUE, but has the ID 0
        # so lets also ask for a valid ID > 0
        $hasDispatch = ($shopwareOrder->getDispatch() instanceof Dispatch) && ($shopwareOrder->getDispatch()->getId() > 0);

        $trackingUrl = '';

        # replace the tracking code variable in our tracking URL
        if (!empty($trackingCode)) {

            # if we have a tracking code,
            # then grab our smarty tracking url template
            $smartyTrackingUrl = (string)($hasDispatch) ? trim($shopwareOrder->getDispatch()->getStatusLink()) : '';

            # fill our smarty url template with
            # real values, so we get a final URL
            $trackingUrl = $this->fillTrackingUrl($smartyTrackingUrl, $trackingCode);
        }


        # now validate if the tracking URL is a valid URL if not, just remove it
        if (filter_var($trackingUrl, FILTER_VALIDATE_URL) === false) {
            $trackingUrl = '';
        }

        return $trackingUrl;
    }


    /**
     * @param $smartyTrackingUrl
     * @param $trackingCode
     * @return array|string|string[]
     */
    private function fillTrackingUrl($smartyTrackingUrl, $trackingCode)
    {
        # assign our defined shopware variables for tracking
        # if one of these is used, then we replace it
        # https://docs.shopware.com/en/shopware-5-en/tutorials-and-faq/tracking-numbers-and-tracking#providing-tracking-urls-in-the-frontend

        $this->smarty->assign(
            'sOrder',
            ['trackingcode' => $trackingCode,]
        );

        $this->smarty->assign(
            'offerPosition',
            ['trackingcode' => $trackingCode,]
        );

        try {
            $trackingUrl = $this->smarty->fetch('string:' . $smartyTrackingUrl);
        } catch (\Exception $ex) {
            $trackingUrl = '';
        }

        # also return any characters that might be left
        # and are not allowed by Mollie
        if ($this->stringContains('{', $trackingUrl)) {
            $trackingUrl = '';
        }

        if ($this->stringContains('}', $trackingUrl)) {
            $trackingUrl = '';
        }

        if ($this->stringContains('<', $trackingUrl)) {
            $trackingUrl = '';
        }

        if ($this->stringContains('>', $trackingUrl)) {
            $trackingUrl = '';
        }

        # hashtag is not allowed by Mollie
        if ($this->stringContains('#', $trackingUrl)) {
            $trackingUrl = '';
        }

        return $trackingUrl;
    }

    /**
     * @param $needle
     * @param $haystack
     * @return bool
     */
    private function stringContains($needle, $haystack)
    {
        if (strpos($haystack, $needle) !== false) {
            return true;
        }

        return false;
    }
}
