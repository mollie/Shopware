<?php

namespace MollieShopware\Components\Transaction;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Gateways\Mollie\MollieGatewayFactory;
use MollieShopware\Models\Transaction;
use Shopware\Models\Order\Order;


class PaymentStatusResolver
{

    /**
     * @var MollieShopSwitcher
     */
    private $shopSwitcher;

    /**
     * @var MollieGatewayFactory
     */
    private $mollieFactory;

    /**
     * @var MollieStatusConverter
     */
    private $statusConverter;

    /**
     * @var OrderService
     */
    private $orderService;


    /**
     * PaymentStatusResolver constructor.
     * @param MollieShopSwitcher $shopSwitcher
     * @param MollieGatewayFactory $mollieFactory
     * @param MollieStatusConverter $statusConverter
     * @param OrderService $orderService
     */
    public function __construct(MollieShopSwitcher $shopSwitcher, MollieGatewayFactory $mollieFactory, MollieStatusConverter $statusConverter, OrderService $orderService)
    {
        $this->shopSwitcher = $shopSwitcher;
        $this->mollieFactory = $mollieFactory;
        $this->statusConverter = $statusConverter;
        $this->orderService = $orderService;
    }


    /**
     * Gets the Mollie payment status of the underlying
     * order or payment of the provided transaction.
     *
     * @param Transaction $transaction
     * @return string
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function fetchPaymentStatus(Transaction $transaction)
    {
        # we start by checking what shop has been used for our transaction.
        # this is required because each sub shop might have a different API key.
        # we do either get the shop ID from the (new) field in the transaction
        # or from the linked Shopware Order (if existing).

        if ($transaction->getShopId() > 0) {
            # we have a value in our field :)
            $shopId = $transaction->getShopId();
        } else {
            # its an old transaction, so the field is empty
            $shopId = $this->getShopIdByOrder($transaction);
        }

        # if we still haven't found a shop,
        # this could be because we have no order...
        # then we have no payment status for this transaction!
        if ($shopId <= 0) {
            return PaymentStatus::MOLLIE_PAYMENT_UNKNOWN;
        }


        # now build a gateway with from the configuration
        # of our found shop
        $gwMollie = $this->buildMollieGateway($shopId);


        if ($transaction->isTypeOrder()) {
            # fetch ORDER and convert the status
            # if we have the ORDER-API
            $mollieOrder = $gwMollie->getOrder($transaction->getMollieOrderId());

            return $this->statusConverter->getMollieOrderStatus($mollieOrder);
        }

        # if we use the PAYMENTS-API, fetch the payment
        # and convert that status
        $molliePayment = $gwMollie->getPayment($transaction->getMolliePaymentId());

        return $this->statusConverter->getMolliePaymentStatus($molliePayment);
    }

    /**
     * @param $shopId
     * @return \MollieShopware\Gateways\Mollie\MollieGateway
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    private function buildMollieGateway($shopId)
    {
        $mollieClient = $this->shopSwitcher->getMollieApi($shopId);

        return $this->mollieFactory->create(
            $mollieClient
        );
    }

    /**
     * @param Transaction $transaction
     * @return int
     */
    private function getShopIdByOrder(Transaction $transaction)
    {
        if (empty($transaction->getOrderNumber())) {
            return 0;
        }

        $order = $this->orderService->getShopwareOrderByNumber($transaction->getOrderNumber());

        if (!$order instanceof Order) {
            return 0;
        }

        return $order->getShop()->getId();
    }

}
