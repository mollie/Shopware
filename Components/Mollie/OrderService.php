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
                'orderId' => $orderId
            ]);
        }
        catch (Exception $ex) {
            // log error
            Logger::log('error', $ex->getMessage(), $ex);
        }

        if (!empty($transaction)) {
            $mollieId = $transaction->getMollieId();
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
                    $unitPrice = $orderDetail->getPrice();

                    // get net price
                    $netPrice = ($unitPrice / ($orderDetail->getTaxRate() + 100)) * 100;

                    // get total amount
                    $totalAmount = round($unitPrice, 2) * $orderDetail->getQuantity();

                    // add tax if net order
                    if ($order->getNet() == true) {
                        $netPrice = $unitPrice;
                        $unitPrice = $unitPrice * (($orderDetail->getTaxRate() + 100) / 100);
                        $totalAmount = ($totalAmount / 100) * ($orderDetail->getTaxRate() + 100);
                    }

                    // get vat amount
                    $vatAmount = (round($netPrice * $orderDetail->getQuantity(), 2) / 100) * $orderDetail->getTaxRate();

                    // clear tax if order is tax free
                    if ($order->getTaxFree()) {
                        $vatAmount = 0;
                        $unitPrice = $netPrice;
                        $totalAmount = round($unitPrice, 2) * $orderDetail->getQuantity();
                    }

                    // build the order line array
                    $orderLine = [
                        'name' => $orderDetail->getArticleName(),
                        'type' => 'physical',
                        'quantity' => $orderDetail->getQuantity(),
                        'unit_price' => round($unitPrice, 2),
                        'net_price' => round($netPrice, 2),
                        'total_amount' => round($totalAmount, 2),
                        'vat_rate' => round($order->getTaxFree() ? 0 : $orderDetail->getTaxRate(), 2),
                        'vat_amount' => round($vatAmount, 3),
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

    /**
     * @return string
     */
    public function checksum()
    {
        $hash = '';
        foreach(func_get_args() as $argument){
            $hash .= $argument;
        }

        return sha1($hash);
    }
}
