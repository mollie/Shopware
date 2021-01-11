<?php

namespace MollieShopware\Gateways\Mollie;

use Mollie\Api\MollieApiClient;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;


class MollieGateway implements MollieGatewayInterface
{

    /**
     * @var MollieApiClient
     */
    private $apiClient;


    /**
     * Cancellation constructor.
     * @param MollieApiClient $mollie
     */
    public function __construct(MollieApiClient $mollie)
    {
        $this->apiClient = $mollie;
    }


    /**
     * @param $orderId
     * @return \Mollie\Api\Resources\Order
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getOrder($orderId)
    {
        $order = $this->apiClient->orders->get(
            $orderId,
            [
                'embed' => 'payments',
            ]
        );

        return $order;
    }

    /**
     * @param $paymentId
     * @return \Mollie\Api\Resources\Payment
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getPayment($paymentId)
    {
        $payment = $this->apiClient->payments->get($paymentId);

        return $payment;
    }

    /**
     * @param Transaction $transaction
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function isMollieCancelledOrFailed(Transaction $transaction)
    {
        if ($transaction->isTypeOrder()) {

            $mollieOrder = $this->apiClient->orders->get(
                $transaction->getMollieId(),
                [
                    'embed' => 'payments',
                ]
            );

            if ($mollieOrder !== null) {

                if ($mollieOrder->isCanceled() === true) {
                    return true;
                }

                if ($mollieOrder->isExpired() === true) {
                    return true;
                }

                if ($mollieOrder->payments() !== null) {
                    return $this->getPaymentCollectionCanceledOrFailed($mollieOrder->payments());
                }
            }

        } else {

            $molliePayment = $this->apiClient->payments->get($transaction->getMolliePaymentId());

            if ($molliePayment->isCanceled() === true || $molliePayment->isFailed() === true) {
                return true;
            }
        }

        return false;
    }

}