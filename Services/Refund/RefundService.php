<?php

namespace MollieShopware\Services\Refund;

use Doctrine\ORM\EntityManager;
use Exception;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Payment as MolliePayment;
use Mollie\Api\Resources\Refund;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Exceptions\RefundFailedException;
use MollieShopware\Gateways\Mollie\MollieGatewayFactory;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\OrderLines;
use MollieShopware\Models\OrderLinesRepository;
use MollieShopware\Models\Transaction;
use Psr\Container\ContainerInterface;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class RefundService implements RefundInterface
{

    /**
     * @var EntityManager
     */
    private $modelManager;

    /**
     * @var OrderLinesRepository
     */
    private $repoOrderLines;

    /**
     * @var \Shopware\Models\Order\Repository
     */
    private $repoOrderStatus;

    /**
     * @var MollieShopSwitcher
     */
    private $shopSwitcher;


    /**
     * @param EntityManager $modelManager
     * @param $container
     */
    public function __construct(EntityManager $modelManager, $container)
    {
        $this->modelManager = $modelManager;
        $this->shopSwitcher = new MollieShopSwitcher($container);

        $this->repoOrderLines = $this->modelManager->getRepository(OrderLines::class);
        $this->repoOrderStatus = $this->modelManager->getRepository(Status::class);
    }


    /**
     * @param Order $order
     * @param Transaction $transaction
     * @return mixed|void
     * @throws RefundFailedException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function refundFullOrder(Order $order, Transaction $transaction)
    {
        # get the configured API client and config for this order
        $mollie = $this->shopSwitcher->getMollieApi($order->getShop()->getId());
        $config = $this->shopSwitcher->getConfig($order->getShop()->getId());

        /** @var MollieGatewayFactory $mollieFactory */
        $mollieFactory = Shopware()->Container()->get('mollie_shopware.gateways.mollie.factory');

        /** @var MollieGatewayInterface $gwMollie */
        $gwMollie = $mollieFactory->create($mollie);


        if ($transaction->isTypeOrder()) {
            $mollieOrder = $gwMollie->getOrder($transaction->getMollieOrderId());

            $refund = $this->sendMollieOrderRefund($order, $mollieOrder, $mollie);
        } else {
            $molliePayment = $gwMollie->getPayment($transaction->getMolliePaymentId());

            if (!$molliePayment->canBeRefunded()) {
                throw new RefundFailedException($order->getNumber(), 'Payment cannot be refunded');
            }

            $refund = $this->sendMolliePaymentRefund($order, $molliePayment, $order->getInvoiceAmount());
        }

        return $this->finishShopwareRefund($order, $config);
    }

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @param $amount
     * @return mixed|void
     * @throws RefundFailedException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function refundPartialOrderAmount(Order $order, Transaction $transaction, $amount)
    {
        # get the configured API client and config for this order
        $mollie = $this->shopSwitcher->getMollieApi($order->getShop()->getId());
        $config = $this->shopSwitcher->getConfig($order->getShop()->getId());

        /** @var MollieGatewayFactory $mollieFactory */
        $mollieFactory = Shopware()->Container()->get('mollie_shopware.gateways.mollie.factory');

        /** @var MollieGatewayInterface $gwMollie */
        $gwMollie = $mollieFactory->create($mollie);


        $molliePayment = null;


        if ($transaction->isTypeOrder()) {

            # if we have an order, a partial refund with customAmount
            # is technically not possible with the API.
            # but what can be done is, to see if we have a valid payment and
            # start our refund on that payment (transaction).
            # in almost all scenarios if nothing goes wrong in Mollie (server side),
            # there is 1 single payment. This payment is either completely paid
            # and has a balance that can be refunded.
            # If its e.g. a Klarna order and is only partially shipped, then the payment
            # can indeed be refunded with that partial amount, but the status is still "authorized".
            # So we search for either a paid or authorized payment and try to do our
            # partial refund on that payment.
            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            $mollieOrder = $gwMollie->getOrder($transaction->getMollieOrderId());

            /** @var Payment $payment */
            foreach ($mollieOrder->payments() as $payment) {
                if ($payment->status === PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED || $payment->status === PaymentStatus::MOLLIE_PAYMENT_PAID) {
                    $molliePayment = $payment;
                    break;
                }
            }
        } else {
            $molliePayment = $gwMollie->getPayment($transaction->getMolliePaymentId());
        }

        if (!$molliePayment instanceof Payment) {
            throw new RefundFailedException($order->getNumber(), 'Mollie Payment for this order has not been found');
        }

        if (!$molliePayment->canBePartiallyRefunded()) {
            throw new RefundFailedException($order->getNumber(), 'Payment cannot be partially refunded!');
        }

        if ($molliePayment->getAmountRemaining() < $amount) {
            throw new RefundFailedException($order->getNumber(), 'Provided refund amount not valid. Only ' . $molliePayment->getAmountRemaining() . ' is left for a refund in this payment.');
        }

        $refund = $this->sendMolliePaymentRefund($order, $molliePayment, $amount);

        $this->finishShopwareRefund($order, $config);
    }

    /**
     * @param Order $order
     * @param Detail $detail
     * @param Transaction $transaction
     * @param $orderLineID
     * @param $quantity
     * @return Refund|null
     * @throws RefundFailedException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function refundPartialOrderItem(Order $order, Detail $detail, Transaction $transaction, $orderLineID, $quantity)
    {
        if (!$transaction->isTypeOrder()) {
            throw new RefundFailedException($order->getNumber(), 'Line Item based partial refunds cannot be done for simple transactions!');
        }

        # get the configured API client and config for this order
        $mollie = $this->shopSwitcher->getMollieApi($order->getShop()->getId());
        $config = $this->shopSwitcher->getConfig($order->getShop()->getId());

        /** @var MollieGatewayFactory $mollieFactory */
        $mollieFactory = Shopware()->Container()->get('mollie_shopware.gateways.mollie.factory');

        /** @var MollieGatewayInterface $gwMollie */
        $gwMollie = $mollieFactory->create($mollie);


        $mollieOrder = $gwMollie->getOrder($transaction->getMollieOrderId());
        $orderLine = $mollieOrder->lines()->get($orderLineID);

        if (!$orderLine instanceof OrderLine) {
            throw new RefundFailedException($order->getNumber(), 'Partial Order refund failed! Line with ID ' . $orderLineID . ' not found in order ' . $order->getNumber() . '!');
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
        $refund = $mollie->orderRefunds->createFor($mollieOrder, $data);

        if ($refund === null) {
            throw new RefundFailedException($order->getNumber(), 'Refund API Call failed for order: ' . $order->getNumber());
        }

        $this->updateRefundedItemsOnOrderDetail($detail, $quantity);

        $this->finishShopwareRefund($order, $config);

        return $refund;
    }

    /**
     * @param Order $order
     * @param MolliePayment $molliePayment
     * @param $amountToRefund
     * @return \Mollie\Api\Resources\BaseResource
     * @throws RefundFailedException
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    private function sendMolliePaymentRefund(Order $order, MolliePayment $molliePayment, $amountToRefund)
    {
        $refund = $molliePayment->refund([
            'amount' => [
                'currency' => $order->getCurrency(),
                'value' => number_format($amountToRefund, 2, '.', '')
            ]
        ]);

        if ($refund === null) {
            throw new RefundFailedException($order->getNumber(), 'Refund API Call failed for order: ' . $order->getNumber());
        }

        return $refund;
    }

    /**
     * @param Order $order
     * @param MollieOrder $mollieOrder
     * @param MollieApiClient $mollie
     * @return Refund
     * @throws RefundFailedException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    private function sendMollieOrderRefund(Order $order, MollieOrder $mollieOrder, MollieApiClient $mollie)
    {
        $mollieShipmentLines = $this->repoOrderLines->getShipmentLines($order);

        /** @var Refund $refund */
        $refund = $mollie->orderRefunds->createFor(
            $mollieOrder,
            [
                'lines' => $mollieShipmentLines
            ]
        );

        if ($refund === null) {
            throw new RefundFailedException($order->getNumber(), 'Refund API Call failed for order: ' . $order->getNumber());
        }

        if (!$order->getDetails()->isEmpty()) {
            foreach ($order->getDetails() as $detail) {
                $this->updateRefundedItemsOnOrderDetail($detail, $detail->getQuantity());
            }
        }

        return $refund;
    }

    /**
     * @param Order $order
     * @param Config $config
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function finishShopwareRefund(Order $order, Config $config)
    {
        # somehow it always says it cannot use modules in the constructor...synthetic service stuff
        # lets just keep it here for now
        $sOrder = Shopware()->Modules()->Order();

        /** @var Status $paymentStatusRefunded */
        $paymentStatusRefunded = $this->repoOrderStatus->find(Status::PAYMENT_STATE_RE_CREDITING);

        // set the payment status
        $order->setPaymentStatus($paymentStatusRefunded);

        // save the order
        $this->modelManager->persist($order);
        $this->modelManager->flush();

        // send status email
        if ($config->isPaymentStatusMailEnabled() && $config->sendRefundStatusMail()) {
            $mail = $sOrder->createStatusMail($order->getId(), $paymentStatusRefunded->getId());

            if ($mail) {
                $sOrder->sendStatusMail($mail);
            }
        }

        return true;
    }

    /**
     * @param $detail
     * @param $quantity
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function updateRefundedItemsOnOrderDetail($detail, $quantity)
    {
        if ($detail->getAttribute() === null) {
            return;
        }

        if (!method_exists($detail->getAttribute(), 'getMollieReturn')) {
            return;
        }

        if (!method_exists($detail->getAttribute(), 'setMollieReturn')) {
            return;
        }

        $mollieReturn = $detail->getAttribute()->getMollieReturn();
        $mollieReturn += $quantity;

        $detail->getAttribute()->setMollieReturn($mollieReturn);

        $this->modelManager->persist($detail->getAttribute());
        $this->modelManager->flush($detail->getAttribute());
    }
}
