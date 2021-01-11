<?php

namespace MollieShopware\Facades\FinishCheckout\Services;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use MollieShopware\Exceptions\MolliePaymentFailedException;
use MollieShopware\Models\Transaction;

class MollieStatusValidator
{

    /**
     * @param Order $order
     * @return bool
     */
    public function didOrderCheckoutSucceed(Order $order)
    {
        if ($order->isCanceled()) {
            return false;
        }

        if ($order->isExpired()) {
            return false;
        }

        if ($order->payments() !== null) {
            return !$this->getPaymentCollectionCanceledOrFailed($order->payments());
        }

        return true;
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    public function didPaymentCheckoutSucceed(Payment $payment)
    {
        if ($payment->isCanceled()) {
            return false;
        }

        if ($payment->isFailed()) {
            return false;
        }
        
        return true;
    }

    /**
     * @param PaymentCollection $payments
     * @return bool
     */
    private function getPaymentCollectionCanceledOrFailed(PaymentCollection $payments)
    {
        $paymentsTotal = $payments->count();
        $canceledPayments = 0;
        $failedPayments = 0;

        if ($paymentsTotal > 0) {
            /** @var \Mollie\Api\Resources\Payment $payment */
            foreach ($payments as $payment) {
                if ($payment->isCanceled() === true) {
                    $canceledPayments++;
                }
                if ($payment->isFailed() === true) {
                    $failedPayments++;
                }
            }

            if ($canceledPayments > 0 || $failedPayments > 0) {
                if (($canceledPayments + $failedPayments) === $paymentsTotal) {
                    return true;
                }
            }
        }

        return false;
    }

}
