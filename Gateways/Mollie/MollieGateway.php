<?php

namespace MollieShopware\Gateways\Mollie;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Issuer;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Shipment;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
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
     * @param MollieApiClient $client
     * @return void
     */
    public function switchClient(MollieApiClient $client)
    {
        $this->apiClient = $client;
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
     * @return Issuer[]
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getIdealIssuers()
    {
        $paymentMethods = $this->apiClient->methods->allActive(
            [
                'include' => 'issuers'
            ]
        );

        $issuers = [];

        foreach ($paymentMethods as $paymentMethod) {

            if ($paymentMethod->id === PaymentMethod::IDEAL) {
                foreach ($paymentMethod->issuers() as $key => $issuer) {
                    $issuers[] = $issuer;
                }
                break;
            }
        }

        return $issuers;
    }

    /**
     * @param Order $mollieOrder
     * @param string $carrier
     * @param string $trackingNumber
     * @param string $trackingUrl
     * @return Shipment
     */
    public function shipOrder(Order $mollieOrder, $carrier, $trackingNumber, $trackingUrl)
    {
        if (empty($trackingNumber)) {
            return $mollieOrder->shipAll();
        }

        $options = [
            'tracking' => [
                'carrier' => $carrier,
                'code' => $trackingNumber,
                'url' => $trackingUrl,
            ],
        ];

        return $mollieOrder->shipAll($options);
    }

}
