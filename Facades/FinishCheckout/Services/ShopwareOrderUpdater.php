<?php

namespace MollieShopware\Facades\FinishCheckout\Services;

use Mollie\Api\Resources\Payment;
use MollieShopware\Components\Config;
use MollieShopware\Models\Transaction;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

class ShopwareOrderUpdater
{

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ModelManager
     */
    private $entityManger;


    /**
     * ShopwareOrderUpdater constructor.
     * @param Config $config
     * @param ModelManager $entityManger
     */
    public function __construct(Config $config, ModelManager $entityManger)
    {
        $this->config = $config;
        $this->entityManger = $entityManger;
    }


    /**
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return mixed
     * @throws \Exception
     */
    public function getFinalTransactionIdFromOrder(\Mollie\Api\Resources\Order $mollieOrder)
    {
        if ($mollieOrder->payments() === null || $mollieOrder->payments()->count() <= 0) {
            throw new \Exception('No Payments found');
        }

        # all our mollie orders need to use the ord_xyz id.
        # this is necessary to avoid orders being created duplicated with (create order AFTER payment),
        # in case that the return URL is invoked multiple times.
        # the incoming mollie ID is the ord_id, so we need to also use this one as lookup in the order
        # and not the tr_xxx number.
        # on the other hand its nice to see where the Orders API has been used.
        $transactionNumber = $mollieOrder->id;

        $molliePayment = $mollieOrder->payments()[0];

        return $this->getTransactionId($transactionNumber, $molliePayment);
    }

    /**
     * @param Payment $payment
     * @return mixed
     */
    public function getFinalTransactionIdFromPayment(Payment $payment)
    {
        # for simple payments we use the payment id tr_xxxx
        # as transaction number in our Shopware order.
        $transactionNumber = $payment->id;

        return $this->getTransactionId($transactionNumber, $payment);
    }

    /**
     * Updates the transaction ID of the provided Shopware order.
     *
     * @param Order $order
     * @param $transactionId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateTransactionId(Order $order, $transactionId)
    {
        $order->setTransactionId($transactionId);

        $this->entityManger->persist($order);
        $this->entityManger->flush($order);
    }

    /**
     * @param Order $swOrder
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Transaction $transaction
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateReferencesFromMollieOrder(Order $swOrder, \Mollie\Api\Resources\Order $mollieOrder, Transaction $transaction)
    {
        if ($mollieOrder->payments() === null || $mollieOrder->payments()->count() <= 0) {
            throw new \Exception('No Payments found');
        }

        foreach ($mollieOrder->lines() as $orderLine) {

            $metadata = json_decode($orderLine->metadata, true);

            if (!is_array($metadata) || !isset($metadata['transaction_item_id'])) {
                continue;
            }

            /** @var \MollieShopware\Models\TransactionItem $transactionItem */
            foreach ($transaction->getItems() as $transactionItem) {

                if ($transactionItem->getId() === (int)$metadata['transaction_item_id']) {

                    $transactionItem->setOrderLineId($orderLine->id);

                    $this->entityManger->persist($transactionItem);
                    $this->entityManger->flush($transactionItem);
                }
            }
        }


        if (!$swOrder->getDetails()->isEmpty() && !$transaction->getItems()->isEmpty()) {

            /** @var Detail $detail */
            foreach ($swOrder->getDetails() as $detail) {

                foreach ($transaction->getItems() as $transactionItem) {

                    if ($detail->getAttribute() === null) {
                        continue;
                    }

                    if (
                        method_exists($detail->getAttribute(), 'getBasketItemId')
                        && method_exists($detail->getAttribute(), 'setMollieTransactionId')
                        && method_exists($detail->getAttribute(), 'setMollieOrderLineId')
                        && (int)$detail->getAttribute()->getBasketItemId() === $transactionItem->getBasketItemId()
                    ) {

                        $detail->getAttribute()->setMollieTransactionId($transaction->getMollieId());
                        $detail->getAttribute()->setMollieOrderLineId($transactionItem->getOrderLineId());

                        $this->entityManger->persist($detail->getAttribute());
                        $this->entityManger->flush($detail->getAttribute());
                    }
                }
            }
        }
    }

    /**
     * @param $transactionNumber
     * @param Payment $payment
     * @return mixed
     */
    private function getTransactionId($transactionNumber, Payment $payment)
    {
        # the merchant can configure that he wants to use
        # a reference from the payment method instead, if existing
        if ($this->config->getTransactionNumberType() === Config::TRANSACTION_NUMBER_TYPE_PAYMENT_METHOD) {

            # if we have a Paypal reference use this
            if (isset($payment->details, $payment->details->paypalReference)) {
                $transactionNumber = $payment->details->paypalReference;
            }

            # if we have a transfer reference use this
            if (isset($payment->details, $payment->details->transferReference)) {
                $transactionNumber = $payment->details->transferReference;
            }
        }

        return $transactionNumber;
    }

}
