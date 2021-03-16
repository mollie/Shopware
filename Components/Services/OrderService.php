<?php

namespace MollieShopware\Components\Services;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use MollieShopware\Components\Logger;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Models\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\History;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;

class OrderService
{
    /** @var ModelManager $modelManager */
    protected $modelManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ModelManager $modelManager
     * @param LoggerInterface $logger
     */
    public function __construct(ModelManager $modelManager, LoggerInterface $logger)
    {
        $this->modelManager = $modelManager;
        $this->logger = $logger;
    }

    /**
     * Get an order by it's id
     *
     * @param int $orderId
     *
     * @return Order $order
     *
     * @throws \Exception
     */
    public function getOrderById($orderId)
    {
        $order = null;

        try {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = $this->modelManager->getRepository(
                Order::class
            );

            /** @var Order $order */
            $order = $orderRepo->find($orderId);
        } catch (\Exception $ex) {

            $this->logger->error(
                'Error when loading order by id: ' . $orderId,
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $order;
    }

    /**
     * Get an order detail by it's id
     *
     * @param int $orderDetailId
     *
     * @return \Shopware\Models\Order\Detail $detail
     *
     * @throws \Exception
     */
    public function getOrderDetailById($orderDetailId)
    {
        $detail = null;

        try {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = $this->modelManager->getRepository(
                \Shopware\Models\Order\Detail::class
            );

            /** @var \Shopware\Models\Order\Detail $detail */
            $detail = $orderRepo->find($orderDetailId);
        } catch (\Exception $ex) {

            $this->logger->error(
                'Error when loading order detail by id: ' . $orderDetailId,
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $detail;
    }

    /**
     * @param string $orderNumber
     *
     * @return Transaction
     *
     * @throws TransactionNotFoundException
     */
    public function getOrderTransactionByNumber($orderNumber)
    {
        /** @var Repository $transactionRepository */
        $transactionRepository = $this->modelManager->getRepository(Transaction::class);
        $transactions = new ArrayCollection($transactionRepository->findBy([
            'orderNumber' => $orderNumber
        ]));

        if ($transactions->count() === 0) {
            throw new TransactionNotFoundException(sprintf('with ordernumber %s', $orderNumber));
        }

        return $transactions->first();
    }

    /**
     * @param $orderNumber
     * @return Order
     */
    public function getShopwareOrderByNumber($orderNumber)
    {
        /** @var \Shopware\Models\Order\Repository $orderRepo */
        $orderRepo = $this->modelManager->getRepository(Order::class);

        /** @var Order $order */
        $order = $orderRepo->findOneBy([
            'number' => $orderNumber
        ]);

        return $order;
    }

    /**
     * Gets the external Mollie order id from a provided Shopware order.
     * The Mollie order id does only exist if the ORDERS-API has been used.
     * The id is searched in the Mollie transaction database table.
     *
     * @param Order $orderId
     * @return null|string
     * @throws \Exception
     */
    public function getMollieOrderId($order)
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
                'orderId' => $order->getId()
            ]);
        } catch (\Exception $ex) {

            $this->logger->error(
                'Error when loading mollie order id',
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        if (!empty($transaction))
            $mollieId = $transaction->getMollieId();

        return $mollieId;
    }

    /**
     * @param int $orderId
     * @param array|null $orderBy
     * @param int|null $offset
     * @param int|null $limit
     * @return array|int|string
     */
    public function getOrderStatusHistory($orderId, $orderBy, $offset, $limit)
    {
        $builder = $this->modelManager->createQueryBuilder();

        $builder->select([
            'history.changeDate',
            'user.name as userName',
            'history.previousOrderStatusId as prevOrderStatusId',
            'history.orderStatusId as currentOrderStatusId',
            'history.previousPaymentStatusId as prevPaymentStatusId',
            'history.paymentStatusId as currentPaymentStatusId',
            'history.comment as comment'
        ]);

        $builder->from(History::class, 'history')
            ->leftJoin('history.user', 'user')
            ->where('history.orderId = ?1')
            ->setParameter(1, $orderId);

        if (!empty($orderBy)) {
            $builder->addOrderBy($orderBy);
        }

        if ($limit !== null) {
            $builder->setFirstResult($offset)->setMaxResults($limit);
        }

        return $builder->getQuery()->getArrayResult();
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
        } catch (\Exception $ex) {

            $this->logger->error(
                'Error when loading mollie payment id of order: ' . $orderId,
                array(
                    'error' => $ex->getMessage(),
                )
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

        /** @var Order $order */
        if ($orderId instanceof Order)
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
                        'order_line_id' => $orderDetail->getId(),
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
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error when loading order lines',
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $items;
    }

    public function getOrderBySessionId(string $sessionId)
    {
        $order = null;

        try {
            /** @var \Shopware\Models\Order\Repository $orderRepo */
            $orderRepo = $this->modelManager->getRepository(
                Order::class
            );

            /** @var Order $order */
            $order = $orderRepo->findOneBy([
                'temporaryId' => $sessionId
            ]);
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error when loading order by session ID: ' . $sessionId,
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $order;
    }

    /**
     * @param string $transactionId
     * @return Order
     * @throws OrderNotFoundException
     */
    public function getOrderByTransactionId(string $transactionId)
    {
        /** @var \Shopware\Models\Order\Repository $orderRepo */
        $orderRepo = $this->modelManager->getRepository(Order::class);

        /** @var Order $order */
        $order = $orderRepo->findOneBy(['transactionId' => $transactionId]);

        if (!$order instanceof Order) {
            throw new OrderNotFoundException('Order for Transaction: ' . $transactionId . ' not found!');
        }

        return $order;
    }

}
