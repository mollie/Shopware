<?php


namespace MollieShopware\Components\Order;


use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Events\Events;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use Psr\Log\LoggerInterface;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class OrderUpdater
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var $sOrder
     */
    private $sOrder;

    /**
     * @var ContainerAwareEventManager
     */
    private $eventManager;


    /**
     * @param Config $config
     * @param $sOrder
     * @param $eventManager
     * @param $logger
     */
    public function __construct(Config $config, $sOrder, $eventManager, $logger)
    {
        $this->config = $config;
        $this->sOrder = $sOrder;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }


    /**
     * @param Order $order
     * @param $status
     * @throws PaymentStatusNotFoundException
     */
    public function updateShopwarePaymentStatus(Order $order, $status)
    {
        $this->updatePaymentStatus(
            $order,
            $status,
            $this->config->isPaymentStatusMailEnabled()
        );
    }

    /**
     * @param Order $order
     * @param $status
     * @throws PaymentStatusNotFoundException
     */
    public function updateShopwarePaymentStatusWithoutMail(Order $order, $status)
    {
        $this->updatePaymentStatus(
            $order,
            $status,
            false
        );
    }

    /**
     * @param Order $order
     * @param $status
     * @throws OrderStatusNotFoundException
     */
    public function updateShopwareOrderStatus(Order $order, $status)
    {
        $this->updateOrderStatus(
            $order,
            $status,
            $this->config->isPaymentStatusMailEnabled()
        );
    }

    /**
     * @param Order $order
     * @param $status
     * @throws OrderStatusNotFoundException
     */
    public function updateShopwareOrderStatusWithoutMail(Order $order, $status)
    {
        $this->updateOrderStatus(
            $order,
            $status,
            false
        );
    }

    /**
     * @param Order $order
     * @param $status
     * @param $sendMail
     * @throws PaymentStatusNotFoundException
     */
    private function updatePaymentStatus(Order $order, $status, $sendMail)
    {
        $shopwareStatus = null;
        $ignoreState = false;

        switch ($status) {

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
                $shopwareStatus = Status::PAYMENT_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $shopwareStatus = $this->config->getAuthorizedPaymentStatusId();
                break;

            case PaymentStatus::MOLLIE_PAYMENT_DELAYED:
                $shopwareStatus = Status::PAYMENT_STATE_DELAYED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PAID:
                $shopwareStatus = Status::PAYMENT_STATE_COMPLETELY_PAID;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $shopwareStatus = Status::PAYMENT_STATE_RE_CREDITING;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $shopwareStatus = Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                # impact on the payment entry in shopware
                # these are only states about the order
                $ignoreState = true;
                break;
        }


        $previousShopwareStatus = $shopwareStatus;

        # send a filter event, so developer can adjust the status that will
        # be used for the shopware payment status
        $shopwareStatus = $this->eventManager->filter(
            Events::UPDATE_ORDER_PAYMENT_STATUS,
            $shopwareStatus,
            array(
                'molliePaymentStatus' => $status,
                'order' => $order,
            )
        );

        if ($previousShopwareStatus !== $shopwareStatus) {
            $this->logger->info('Filter Event changed Payment Status for Order ' . $order->getNumber(),
                array(
                    'data' => array(
                        'previousStatus' => $previousShopwareStatus,
                        'newStatus' => $shopwareStatus
                    )
                )
            );

            # avoid state ignoring, because we have
            # a custom handling now. so process everything the
            # other plugin says
            $ignoreState = false;
        }

        if ($ignoreState) {
            return;
        }

        if ($shopwareStatus === null) {
            throw new PaymentStatusNotFoundException($status);
        }

        $this->sOrder->setPaymentStatus(
            $order->getId(),
            $shopwareStatus,
            $sendMail
        );
    }

    /**
     * @param Order $order
     * @param $mollieStatus
     * @param $sendMail
     * @throws OrderStatusNotFoundException
     */
    private function updateOrderStatus(Order $order, $mollieStatus, $sendMail)
    {
        $shopwareStatus = null;
        $ignoreState = false;

        switch ($mollieStatus) {
            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $shopwareStatus = Status::ORDER_STATE_COMPLETED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $shopwareStatus = Status::ORDER_STATE_CANCELLED_REJECTED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
            case PaymentStatus::MOLLIE_PAYMENT_DELAYED:
            case PaymentStatus::MOLLIE_PAYMENT_PAID:
            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                # these payment status entries have no
                # impact on the order status at the moment
                $ignoreState = true;
                break;
        }


        $previousShopwareStatus = $shopwareStatus;

        # send a filter event, so developer can adjust the status that will
        # be used for the shopware order status
        $shopwareStatus = $this->eventManager->filter(
            Events::UPDATE_ORDER_STATUS,
            $shopwareStatus,
            array(
                'mollieOrderStatus' => $mollieStatus,
                'order' => $order,
            )
        );

        if ($previousShopwareStatus !== $shopwareStatus) {
            $this->logger->info('Filter Event changed Order Status for Order ' . $order->getNumber(),
                array(
                    'data' => array(
                        'previousStatus' => $previousShopwareStatus,
                        'newStatus' => $shopwareStatus
                    )
                )
            );

            # avoid state ignoring, because we have
            # a custom handling now. so process everything the
            # other plugin says
            $ignoreState = false;
        }


        if ($ignoreState) {
            return;
        }

        if ($shopwareStatus === null) {
            throw new OrderStatusNotFoundException($mollieStatus);
        }

        $this->sOrder->setOrderStatus(
            $order->getId(),
            $shopwareStatus,
            $sendMail
        );
    }

}