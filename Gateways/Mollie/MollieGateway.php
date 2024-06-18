<?php

namespace MollieShopware\Gateways\Mollie;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Issuer;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Profile;
use Mollie\Api\Resources\Shipment;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Gateways\Mollie\Exceptions\InvalidOrderAmountException;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use MollieShopware\Services\MollieOrderRequestAnonymizer\MollieOrderRequestAnonymizer;
use Psr\Log\LoggerInterface;

class MollieGateway implements MollieGatewayInterface
{

    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var MollieOrderRequestAnonymizer
     */
    private $mollieOrderAnonymizer;
    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * Cancellation constructor.
     * @param MollieApiClient $mollie
     */
    public function __construct(MollieApiClient $mollie, MollieOrderRequestAnonymizer $mollieOrderAnonymizer, LoggerInterface $logger)
    {
        $this->apiClient = $mollie;
        $this->mollieOrderAnonymizer = $mollieOrderAnonymizer;
        $this->logger = $logger;
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
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return string
     */
    public function getProfileId()
    {
        $profile = $this->apiClient->profiles->get('me');

        if ($profile === null) {
            return '';
        }

        return (string)$profile->id;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return string
     */
    public function getOrganizationId()
    {
        $profile = $this->apiClient->profiles->get('me');

        if ($profile === null) {
            return '';
        }

        # the organization is in a full dashboard URL
        # so we grab it, and extract that slug with the organization id
        $orgId = (string)$profile->_links->dashboard->href;

        $parts = explode('/', $orgId);

        foreach ($parts as $part) {
            if (strpos($part, 'org_') === 0) {
                $orgId = $part;
                break;
            }
        }

        return (string)$orgId;
    }

    /**
     * @param $orderId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return \Mollie\Api\Resources\Order
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
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return \Mollie\Api\Resources\Payment
     */
    public function getPayment($paymentId)
    {
        $payment = $this->apiClient->payments->get($paymentId);

        return $payment;
    }


    /**
     * @param array $requestData
     * @throws ApiException
     * @throws InvalidOrderAmountException
     * @return Order
     */
    public function createOrder(array $requestData)
    {
        try {
            return $this->apiClient->orders->create($requestData);
        } catch (ApiException $ex) {
            $anonymizedRequest = $this->mollieOrderAnonymizer->anonymize($requestData);
            $this->logger->debug('Details for the ApiException: '.$ex->getMessage(), $anonymizedRequest);

            throw $ex;
        }
    }

    public function createPayment(array $requestData)
    {
        try {
            return $this->apiClient->payments->create($requestData);
        } catch (ApiException $ex) {
            $anonymizedRequest = $this->mollieOrderAnonymizer->anonymize($requestData);
            $this->logger->debug('Details for the ApiException: '.$ex->getMessage(), $anonymizedRequest);

            throw $ex;
        }
    }

    /**
     * @param $mollieId
     * @param $orderNumber
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return void
     */
    public function updateOrderNumber($mollieId, $orderNumber)
    {
        $this->apiClient->orders->update(
            $mollieId,
            [
                'orderNumber' => $orderNumber,
            ]
        );
    }

    /**
     * @param $paymentId
     * @param $description
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return void
     */
    public function updatePaymentDescription($paymentId, $description)
    {
        $this->apiClient->payments->update(
            $paymentId,
            [
                'description' => $description,
            ]
        );
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

    /**
     * @param Order $mollieOrder
     * @param $lineId
     * @param $quantity
     * @param $carrier
     * @param $trackingNumber
     * @param $trackingUrl
     * @return mixed|Shipment
     */
    public function shipOrderPartially(Order $mollieOrder, $lineId, $quantity, $carrier, $trackingNumber, $trackingUrl)
    {
        $data = [
            'lines' => [
                [
                    'id' => $lineId,
                    'quantity' => $quantity,
                ]
            ]
        ];

        if (!empty($trackingNumber)) {
            $data['tracking'] = [
                'carrier' => $carrier,
                'code' => $trackingNumber,
                'url' => $trackingUrl,
            ];
        }

        return $mollieOrder->createShipment($data);
    }
}
