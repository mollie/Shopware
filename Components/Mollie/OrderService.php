<?php

	// Mollie Shopware Plugin Version: 1.3.5

namespace MollieShopware\Components\Mollie;

use MollieShopware\Models\Transaction;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Mollie\Api\Resources\Order as MollieOrder;
use Exception;

class OrderService
{
    /**
     *
     * @var ModelManager $modelManager
     */
    private $modelManager;

    /**
     * Constructor
     *
     * @param ModelManager $modelManager
     */
    public function __construct(ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    /**
     * Get an order by it's id
     *
     * @param int $orderId
     * @return Order $order
     */
    public function getOrderById($orderId)
    {
        $order = null;

        try {
            // get order repository
            $orderRepo = $this->modelManager->getRepository(Order::class);

            // find order
            $order = $orderRepo->findOneBy([
                'id' => $orderId
            ]);
        }
        catch (Exception $ex) {
            // log error
            if ($order != null) {
                $orderRepo->addException($order, $ex);
            }
        }

        return $order;
    }

    /**
     * @param Shopware\Models\Order\Order $order
     * @return Transaction $transaction
     */
    public function getTransaction(Shopware\Models\Order\Order $order)
    {
        $transaction = null;

        try {
            $transactionRepo = $this->modelManager->getRepository(Transaction::class);
            $transaction = $transactionRepo->findOneBy([
                'order_id' => $order->getId()
            ]);
        }
        catch (Exception $ex) {
            // @todo Handle exception
        }

        return $transaction;
    }

    /**
     * @param Shopware\Models\Order\Order $order
     * @return string $mollieId
     */
    public function getMollieOrderId(Shopware\Models\Order\Order $order)
    {
        // vars
        $mollieId = null;

        // get transaction
        $transaction = $this->getTransaction($order);

        // get mollie id
        if (!empty($transaction)) {
            $mollieId = $transaction->getMollieId();
        }

        return $mollieId;
    }

    /**
     * @param MollieOrder $mollieOrder
     * @return array $orderlines
     */
    public function getMollieOrderLines(MollieOrder $mollieOrder)
    {
        // vars
        $orderLines = [];

        // add data to orderlines array
        if (count($mollieOrder->lines)) {
            foreach ($mollieOrder->lines as $line) {
                $orderLines[] = [
                    'id' => $line->id,
                    'name' => $line->name,
                    'sku' => $line->sku,
                    'isCancelable' => $line->isCancelable,
                    'quantity' => $line->quantity,
                    'quantityShipped' => $line->quantityShipped,
                    'quantityRefunded' => $line->quantityRefunded,
                    'shippableQuantity' => $line->shippableQuantity,
                    'refundableQuantity' => $line->refundableQuantity,
                    'totalAmountValue' => $line->totalAmount->value,
                    'totalAmountCurrency' => $line->totalAmount->currency
                ];
            }
        }

        return $orderLines;
    }

    /**
     * @return string
     */
    public function checksum()
    {
        // vars
        $hash = '';

        // add arguments to hash
        foreach(func_get_args() as $argument){
            $hash .= $argument;
        }

        return sha1($hash);
    }
}
