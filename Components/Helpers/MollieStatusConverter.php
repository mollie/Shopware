<?php

namespace MollieShopware\Components\Helpers;

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Services\PaymentService;

class MollieStatusConverter
{

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var MollieRefundStatus
     */
    private $refundStatus;


    /**
     * MollieStatusConverter constructor.
     * @param PaymentService $paymentService
     * @param MollieRefundStatus $refundStatus
     */
    public function __construct(PaymentService $paymentService, MollieRefundStatus $refundStatus)
    {
        $this->paymentService = $paymentService;
        $this->refundStatus = $refundStatus;
    }


    /**
     * @param Order $order
     * @return string
     */
    public function getMollieOrderStatus(Order $order)
    {
        /** @var array $paymentsResult */
        $paymentsResult = $this->paymentService->getPaymentsResultForOrder($order);

        $targetStatus = '';

        if ($paymentsResult['total'] > 0) {

            // fully paid
            if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_PAID] == $paymentsResult['total']) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_PAID;
            }

            // fully authorized
            if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED] == $paymentsResult['total']) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED;
            }

            // fully canceled
            if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_CANCELED] == $paymentsResult['total']) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_CANCELED;
            }

            // fully open
            if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_OPEN] == $paymentsResult['total']) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_COMPLETED;
            }

            if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_FAILED] == $paymentsResult['total']) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_FAILED;
            }

            if ($paymentsResult[PaymentStatus::MOLLIE_PAYMENT_EXPIRED] == $paymentsResult['total']) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_EXPIRED;
            }

        } else {

            if ($order->isPaid()) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_PAID;
            } else if ($order->isAuthorized()) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED;
            } else if ($order->isCanceled()) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_CANCELED;
            } else if ($order->isCompleted()) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_COMPLETED;
            } else if ($order->isExpired()) {
                $targetStatus = PaymentStatus::MOLLIE_PAYMENT_EXPIRED;
            }

        }


        # i dont know if that can happen in both ways?
        # but it was definitely necessary to add it
        if ($this->refundStatus->isOrderFullyRefunded($order)) {
            $targetStatus = PaymentStatus::MOLLIE_PAYMENT_REFUNDED;
        }

        if ($this->refundStatus->isOrderPartiallyRefunded($order)) {
            $targetStatus = PaymentStatus::MOLLIE_PAYMENT_REFUNDED;
        }

        return $targetStatus;
    }

    /**
     * @param Payment $payment
     * @return string
     */
    public function getMolliePaymentStatus(Payment $payment)
    {
        $refundStatus = new MollieRefundStatus();

        $targetState = '';

        if ($refundStatus->isPaymentFullyRefunded($payment)) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_REFUNDED;
        } else if ($refundStatus->isPaymentPartiallyRefunded($payment)) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_REFUNDED;
        } else if ($payment->isPaid()) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_PAID;
        } elseif ($payment->isPending())
            $targetState = PaymentStatus::MOLLIE_PAYMENT_PENDING;
        elseif ($payment->isAuthorized()) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED;
        } elseif ($payment->isOpen()) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_OPEN;
        } elseif ($payment->isCanceled()) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_CANCELED;
        } elseif ($payment->isExpired()) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_EXPIRED;
        } elseif ($payment->isFailed()) {
            $targetState = PaymentStatus::MOLLIE_PAYMENT_FAILED;
        }

        return $targetState;
    }

}