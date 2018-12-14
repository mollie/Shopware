<?php

	// Mollie Shopware Plugin Version: 1.3.9.3

namespace MollieShopware\Components\Mollie;

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
            if ($order != null) {
                $orderRepo->addException($order, $ex);
            }
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
            if ($order != null) {
                $orderRepo->addException($order, $ex);
            }
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
            if ($transaction != null) {
                $transactionRepo->addException($transaction, $ex);
            }
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
                    $unitPrice = $orderDetail->getPrice();

                    // get net price
                    $netPrice = ($unitPrice / ($orderDetail->getTaxRate() + 100)) * 100;

                    // add tax if net order
                    if ($order->getNet() == true) {
                        $netPrice = $unitPrice;
                        $unitPrice = $unitPrice * (($orderDetail->getTaxRate() + 100) / 100);
                    }

                    // get vat amount
                    $vatAmount = ($unitPrice * $orderDetail->getQuantity()) - ($netPrice * $orderDetail->getQuantity());

                    // clear tax if order is tax free
                    if ($order->getTaxFree()) {
                        $vatAmount = 0;
                        $unitPrice = $netPrice;
                    }

                    // build the order line array
                    $orderLine = [
                        'name' => $orderDetail->getArticleName(),
                        'type' => 'physical',
                        'quantity' => $orderDetail->getQuantity(),
                        'unit_price' => round($unitPrice, 2),
                        'net_price' => round($netPrice, 2),
                        'total_amount' => round($unitPrice * $orderDetail->getQuantity(), 2),
                        'vat_rate' => ($vatAmount > 0 || $vatAmount < 0 ? $orderDetail->getTaxRate() : 0),
                        'vat_amount' => round($vatAmount, 2),
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
            // @todo handle exception for orderlines
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
