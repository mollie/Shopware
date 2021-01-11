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

        $molliePayment = $mollieOrder->payments()[0];

        $this->updateReferencesFromMolliePayment($swOrder, $molliePayment);
    }

    /**
     * @param Order $order
     * @param Payment $payment
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateReferencesFromMolliePayment(Order $order, Payment $payment)
    {
        # use payment id (tr_xxxx);
        $transactionNumber = $payment->id;

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

        $order->setTransactionId($transactionNumber);

        $this->entityManger->persist($order);
        $this->entityManger->flush($order);
    }

}