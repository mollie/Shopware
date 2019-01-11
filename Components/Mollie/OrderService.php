<?php

// Mollie Shopware Plugin Version: 1.3.12

namespace MollieShopware\Components\Mollie;

use MollieShopware\Components\Logger;
use MollieShopware\Models\Transaction;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
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
            Logger::log('error', $ex->getMessage(), $ex);
        }

        return $order;
    }

    /**
     * Get an order by it's number
     *
     * @param string $orderNumber
     * @return Order $order
     */
    public function getOrderByNumber($orderNumber)
    {
        $order = null;

        try {
            // get order repository
            $orderRepo = $this->modelManager->getRepository(Order::class);

            // find order
            $order = $orderRepo->findOneBy([
                'number' => $orderNumber
            ]);
        }
        catch (Exception $ex) {
            // log error
            Logger::log('error', $ex->getMessage(), $ex);
        }

        return $order;
    }

    /**
     * @param $orderId
     * @return null
     */
    public function getMollieOrderId($orderId)
    {
        $mollieId = null;
        $transaction = null;

        try {
            $transactionRepo = $this->modelManager->getRepository(Transaction::class);
            $transaction = $transactionRepo->findOneBy([
                'order_id' => $orderId
            ]);
        }
        catch (Exception $ex) {
            // log error
            Logger::log('error', $ex->getMessage(), $ex);
        }

        if (!empty($transaction)) {
            $mollieId = $transaction->getMollieID();
        }

        return $mollieId;
    }

    public function getOrderLines($orderId)
    {
        // vars
        $order = null;
        $items = [];

        // get order
        if ($orderId instanceof Order)
            $order = $orderId;
        else
            $this->getOrderById($orderId);

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
            // write exception to log
            Logger::log('error', $ex->getMessage(), $ex);
        }

        return $items;
    }
}