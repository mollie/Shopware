<?php

// Mollie Shopware Plugin Version: 1.4.8

namespace MollieShopware\Components\Services;

use MollieShopware\Components\Logger;

class OrderService
{
    /** @var \Shopware\Components\Model\ModelManager $modelManager */
    protected $modelManager;

    /**
     * Constructor
     *
     * @param \Shopware\Components\Model\ModelManager $modelManager
     */
    public function __construct(\Shopware\Components\Model\ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    /**
     * Get an order by it's id
     *
     * @param int $orderId
     *
     * @return \Shopware\Models\Order\Order $order
     *
     * @throws \Exception
     */
    public function getOrderById($orderId)
    {
        $order = null;

        try {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = $this->modelManager->getRepository(
                \Shopware\Models\Order\Order::class
            );

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderRepo->find($orderId);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $order;
    }

    /**
     * Get an order by it's number
     *
     * @param string $orderNumber
     *
     * @return \Shopware\Models\Order\Order $order
     *
     * @throws \Exception
     */
    public function getOrderByNumber($orderNumber)
    {
        $order = null;

        try {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = $this->modelManager->getRepository(
                \Shopware\Models\Order\Order::class
            );

            /** @var \Shopware\Models\Order\Order $order */
            $order = $orderRepo->findOneBy([
                'number' => $orderNumber
            ]);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $order;
    }

    /**
     * Get Mollie order ID for order
     *
     * @param $orderId
     *
     * @return null|string
     *
     * @throws \Exception
     */
    public function getMollieOrderId($orderId)
    {
        $mollieId = null;
        $transaction = null;

        try {
            /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
            $transactionRepo = $this->modelManager->getRepository(
                \MollieShopware\Models\Transaction::class
            );

            /** @var \MollieShopware\Models\Transaction $transaction */
            $transaction = $transactionRepo->findOneBy([
                'orderId' => $orderId
            ]);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        if (!empty($transaction))
            $mollieId = $transaction->getMollieId();

        return $mollieId;
    }

    /**
     * Get mollie payment ID for order
     *
     * @param $orderId
     *
     * @return null|string
     *
     * @throws \Exception
     */
    public function getMolliePaymentId($orderId)
    {
        $molliePaymentId = null;
        $transaction = null;

        try {
            /** @var \MollieShopware\Models\TransactionRepository $transactionRepo */
            $transactionRepo = $this->modelManager->getRepository(
                \MollieShopware\Models\Transaction::class
            );

            /** @var \MollieShopware\Models\Transaction $transaction */
            $transaction = $transactionRepo->findOneBy([
                'orderId' => $orderId
            ]);
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        if (!empty($transaction))
            $molliePaymentId = $transaction->getMolliePaymentId();

        return $molliePaymentId;
    }

    /**
     * @param $orderId
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOrderLines($orderId)
    {
        $order = null;
        $items = [];

        /** @var \Shopware\Models\Order\Order $order */
        if ($orderId instanceof \Shopware\Models\Order\Order)
            $order = $orderId;
        else
            $order = $this->getOrderById($orderId);

        try {
            $orderDetails = $order->getDetails();

            if (!empty($orderDetails)) {
                foreach ($orderDetails as $orderDetail) {
                    // get the unit price
                    $unitPrice = round($orderDetail->getPrice(), 2);

                    // get net price
                    $netPrice = ($unitPrice / ($orderDetail->getTaxRate() + 100)) * 100;

                    // add tax if net order
                    if ($order->getNet()) {
                        $netPrice = $unitPrice;
                        $unitPrice = $unitPrice * (($orderDetail->getTaxRate() + 100) / 100);
                    }

                    // clear tax if order is tax free
                    if ($order->getTaxFree())
                        $unitPrice = $netPrice;

                    // get total amount
                    $totalAmount = $unitPrice * $orderDetail->getQuantity();

                    // get vat amount
                    $vatAmount = $totalAmount * ($orderDetail->getTaxRate() / ($orderDetail->getTaxRate() + 100));

                    if ($order->getTaxFree())
                        $vatAmount = 0;

                    // build the order line array
                    $orderLine = [
                        'name' => $orderDetail->getArticleName(),
                        'type' => 'physical',
                        'quantity' => $orderDetail->getQuantity(),
                        'unit_price' => $unitPrice,
                        'net_price' => $netPrice,
                        'total_amount' => $totalAmount,
                        'vat_rate' => $order->getTaxFree() ? 0 : $orderDetail->getTaxRate(),
                        'vat_amount' => $vatAmount,
                    ];

                    // set the order line type
                    if (strstr($orderDetail->getNumber(), 'surcharge'))
                        $orderLine['type'] = 'surcharge';

                    if (strstr($orderDetail->getNumber(), 'discount'))
                        $orderLine['type'] = 'discount';

                    if ($orderDetail->getEsdArticle() > 0)
                        $orderLine['type'] = 'digital';

                    if ($orderDetail->getMode() == 2)
                        $orderLine['type'] = 'discount';

                    if ($unitPrice < 0)
                        $orderLine['type'] = 'discount';

                    // add the order line to items
                    $items[] = $orderLine;
                }
            }
        }
        catch (\Exception $ex) {
            Logger::log(
                'error',
                $ex->getMessage(),
                $ex
            );
        }

        return $items;
    }
}