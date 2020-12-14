<?php


namespace MollieShopware\Components\Order;


use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class OrderUpdater
{

    /**
     * @var Config
     */
    private $config;

    /**
     * @var $sOrder
     */
    private $sOrder;


    /**
     * OrderUpdater constructor.
     * @param Config $config
     * @param $sOrder
     */
    public function __construct(Config $config, $sOrder)
    {
        $this->config = $config;
        $this->sOrder = $sOrder;
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
        $targetState = null;
        $ignoreState = false;
        
        switch ($status) {

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
                $targetState = Status::PAYMENT_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $targetState = $this->config->getAuthorizedPaymentStatusId();
                break;

            case PaymentStatus::MOLLIE_PAYMENT_DELAYED:
                $targetState = Status::PAYMENT_STATE_DELAYED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PAID:
                $targetState = Status::PAYMENT_STATE_COMPLETELY_PAID;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $targetState = Status::PAYMENT_STATE_RE_CREDITING;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $targetState = Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                # impact on the payment entry in shopware
                # these are only states about the order
                $ignoreState = true;
                break;
        }

        if ($ignoreState) {
            return;
        }

        if ($targetState === null) {
            throw new PaymentStatusNotFoundException($status);
        }

        $this->sOrder->setPaymentStatus(
            $order->getId(),
            $targetState,
            $sendMail
        );
    }

    /**
     * @param Order $order
     * @param $status
     * @param $sendMail
     * @throws OrderStatusNotFoundException
     */
    private function updateOrderStatus(Order $order, $status, $sendMail)
    {
        $targetState = null;
        $ignoreState = false;

        switch ($status) {
            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $targetState = Status::ORDER_STATE_COMPLETED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $targetState = Status::ORDER_STATE_CANCELLED_REJECTED;
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

        if ($ignoreState) {
            return;
        }

        if ($targetState === null) {
            throw new OrderStatusNotFoundException($status);
        }

        $this->sOrder->setOrderStatus(
            $order->getId(),
            $targetState,
            $sendMail
        );
    }

}