<?php

namespace MollieShopware\Components\Mollie;

use MollieShopware\Gateways\MollieGatewayInterface;
use Shopware\Models\Dispatch\Dispatch;
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
     * @param Order $order
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrder(Order $order, \Mollie\Api\Resources\Order $mollieOrder)
    {
        # unfortunately instanceOf is not enough
        # an empty dispatch that does not exist, return TRUE, but has the ID 0
        # so lets also ask for a valid ID > 0
        $hasDispatch = ($order->getDispatch() instanceof Dispatch) && ($order->getDispatch()->getId() > 0);

        $shippingCarrier = (string)($hasDispatch) ? trim($order->getDispatch()->getName()) : '-';
        $trackingCode = (string)trim($order->getTrackingCode());
        $trackingUrl = '';

        # replace the tracking code variable in our tracking URL
        if (!empty($trackingCode)) {

            # if we have a tracking code,
            # then grab our smarty tracking url template
            $smartyTrackingUrl = (string)($hasDispatch) ? trim($order->getDispatch()->getStatusLink()) : '';

            # fill our smarty url template with
            # real values, so we get a final URL
            $trackingUrl = $this->fillTrackingUrl($smartyTrackingUrl, $trackingCode);
        }


        # now validate if the tracking URL is a valid URL if not, just remove it
        if (filter_var($trackingUrl, FILTER_VALIDATE_URL) === FALSE) {
            $trackingUrl = '';
        }

        return $this->gwMollie->shipOrder(
            $mollieOrder,
            $shippingCarrier,
            $trackingCode,
            $trackingUrl
        );
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
