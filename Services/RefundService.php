<?php

namespace MollieShopware\Services;

use Doctrine\ORM\EntityManager;
use Exception;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\Payment as MolliePayment;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Refund;
use MollieShopware\Models\OrderLines;
use MollieShopware\Models\OrderLinesRepository;
use MollieShopware\Traits\MollieApiClientTrait;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class RefundService
{
    use MollieApiClientTrait;

    /**
     * @var EntityManager
     */
    private $modelManager;

    public function __construct(EntityManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    /**
     * Refund a Mollie order
     *
     * @param Order       $order
     * @param MollieOrder $mollieOrder
     *
     * @return bool|Refund
     * @throws \Exception
     *
     */
    public function refundOrder(Order $order, MollieOrder $mollieOrder)
    {
        $apiClient = $this->getMollieApi($order->getShop()->getId());

        if (empty($this->modelManager) || $apiClient === null) {
            return false;
        }

        /** @var OrderLinesRepository $mollieOrderLinesRepo */
        $mollieOrderLinesRepo = $this->modelManager->getRepository(OrderLines::class);

        $mollieShipmentLines = $mollieOrderLinesRepo->getShipmentLines($order);

        /** @var Refund $refund */
        $refund = $apiClient->orderRefunds->createFor(
            $mollieOrder,
            [
                'lines' => $mollieShipmentLines
            ]
        );

        if ($refund !== null) {
            $this->processRefund($order);

            if ($order->getDetails()->isEmpty() === false) {
                foreach ($order->getDetails() as $detail) {
                    $this->updateRefundedItemsOnOrderDetail($detail, $detail->getQuantity());
                }
            }
        }

        return $refund;
    }

    /**
     * Refund a Mollie order
     *
     * @param Order       $order
     * @param MollieOrder $mollieOrder
     *
     * @return bool|Refund
     * @throws \Exception
     *
     */
    public function partialRefundOrder(
        Order $order,
        Detail $detail,
        MollieOrder $mollieOrder,
        OrderLine $orderLine,
        $quantity = 1
    ) {
        $apiClient = $this->getMollieApi($order->getShop()->getId());

        if (empty($this->modelManager) || $apiClient === null) {
            return false;
        }

        $data = [
            'lines' => [
                [
                    'id' => $orderLine->id,
                    'quantity' => $quantity,
                ],
            ]
        ];

        /** @var Refund $refund */
        $refund = $apiClient->orderRefunds->createFor($mollieOrder, $data);

        if ($refund !== null) {
            $this->processRefund($order);
            $this->updateRefundedItemsOnOrderDetail($detail, $quantity);
        }

        return $refund;
    }

    /**
     * Refund a Mollie payment
     *
     * @param Order         $order
     * @param MolliePayment $molliePayment
     *
     * @return Mollie\Api\Resources\BaseResource
     *
     * @throws \Exception
     */
    public function refundPayment(Order $order, MolliePayment $molliePayment)
    {
        $refund = $molliePayment->refund(
            [
                'amount' => [
                    'currency' => $order->getCurrency(),
                    'value' => number_format($order->getInvoiceAmount(), 2, '.', '')
                ]
            ]
        );

        if ($refund !== null) {
            $this->processRefund($order);
        }

        return $refund;
    }

    /**
     * Refund a Mollie payment
     *
     * @param Order         $order
     * @param MolliePayment $molliePayment
     * @param float         $amountToRefund
     *
     * @return Mollie\Api\Resources\BaseResource
     *
     * @throws \Exception
     */
    public function partialRefundPayment(Order $order, MolliePayment $molliePayment, $amountToRefund)
    {
        $refund = $molliePayment->refund([
            'amount' => [
                'currency' => $order->getCurrency(),
                'value' => number_format($amountToRefund, 2, '.', '')
            ]
        ]);

        if ($refund !== null) {
            $this->processRefund($order);
        }

        return $refund;
    }

    /**
     * Send the status e-mail
     *
     * @param Order $order
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function processRefund(Order $order)
    {
        if (empty($this->config) || empty($this->modelManager)) {
            return false;
        }

        /** @var \Shopware\Models\Order\Repository $orderStatusRepo */
        $orderStatusRepo = $this->modelManager->getRepository(Status::class);

        /** @var Status $paymentStatusRefunded */
        $paymentStatusRefunded = $orderStatusRepo->find(Status::PAYMENT_STATE_RE_CREDITING);

        // set the payment status
        $order->setPaymentStatus($paymentStatusRefunded);

        // save the order
        $this->modelManager->persist($order);
        $this->modelManager->flush();

        // send status email
        if ($this->config->isPaymentStatusMailEnabled() && $this->config->sendRefundStatusMail()) {
            $mail = Shopware()->Modules()->Order()->createStatusMail(
                $order->getId(),
                $paymentStatusRefunded->getId()
            )
            ;

            if ($mail) {
                Shopware()->Modules()->Order()->sendStatusMail($mail);
            }
        }

        return true;
    }

    public function updateRefundedItemsOnOrderDetail($detail, $quantity)
    {
        if (
            $detail->getAttribute() !== null
            && method_exists($detail->getAttribute(), 'getMollieReturn')
            && method_exists($detail->getAttribute(), 'setMollieReturn')
        ) {
            $mollieReturn = $detail->getAttribute()->getMollieReturn();
            $mollieReturn += $quantity;

            $detail->getAttribute()->setMollieReturn($mollieReturn);

            try {
                $this->modelManager->persist($detail->getAttribute());
                $this->modelManager->flush($detail->getAttribute());
            } catch (Exception $e) {
                //
            }
        }
    }
}