<?php

namespace MollieShopware\Components\Order;

use _PhpScoperd1ad3ba9842f\GuzzleHttp\TransferStats;
use Doctrine\ORM\EntityManager;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\CurrentCustomer;
use MollieShopware\Components\Services\BasketService;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Models\Order\Order;

class OrderCancellation
{

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var BasketService
     */
    private $basketService;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * OrderCancellation constructor.
     * @param Config $config
     * @param EntityManager $entityManager
     * @param OrderService $orderService
     * @param BasketService $basketService
     * @param PaymentService $paymentService
     * @param OrderUpdater $orderUpdater
     */
    public function __construct(Config $config, EntityManager $entityManager, OrderService $orderService, BasketService $basketService, PaymentService $paymentService, OrderUpdater $orderUpdater)
    {
        $this->config = $config;
        $this->repoTransactions = $entityManager->getRepository(Transaction::class);
        $this->orderService = $orderService;
        $this->basketService = $basketService;
        $this->paymentService = $paymentService;
        $this->orderUpdater = $orderUpdater;
    }


    /**
     * @param $transactionNumber
     * @throws \Exception
     */
    public function cancelAndRestoreByTransaction($transactionNumber)
    {
        if (empty($transactionNumber)) {
            return;
        }

        $transaction = $this->repoTransactions->find($transactionNumber);

        if (!$transaction instanceof Transaction) {
            return;
        }

        $orderNumber = $transaction->getOrderNumber();
        $swOrder = $this->orderService->getShopwareOrderByNumber($orderNumber);

        $this->cancelAndRestoreByOrder($swOrder);
    }

    /**
     * @param $swOrder
     * @throws \Exception
     */
    public function cancelAndRestoreByOrder($swOrder)
    {
        if (!$swOrder instanceof Order) {
            return;
        }

        # restore the cart, otherwise it would be empty

        # it's important to restore the order before the placed order is cancelled
        # otherwise the original quantity of the line items can't be restored
        $currentCustomer = new CurrentCustomer(Shopware()->Session(), Shopware()->Models());
        if ((int)$currentCustomer->getCurrentId() === (int)$swOrder->getCustomer()->getId()) {
            $this->restoreCartFromOrder($swOrder);
        }


        # make sure we have all status data cancelled as expected
        $this->cancelPlacedOrder($swOrder);
    }

    /**
     * Cancels the payment status of the provided order
     * and also updates the order status and stock if configured
     * in the plugin configuration.
     *
     * @param Order $order
     * @throws \Exception
     */
    public function cancelPlacedOrder($order)
    {
        $this->orderUpdater->updateShopwarePaymentStatusWithoutMail(
            $order,
            PaymentStatus::MOLLIE_PAYMENT_CANCELED
        );

        if ($this->config->cancelFailedOrders()) {
            $this->orderUpdater->updateShopwareOrderStatusWithoutMail(
                $order,
                PaymentStatus::MOLLIE_PAYMENT_CANCELED
            );

            if ($this->config->autoResetStock()) {
                $this->paymentService->resetStock($order);
            }

            if ($this->config->resetInvoiceAndShipping()) {
                $this->paymentService->resetInvoiceAndShipping($order);
            }
        }
    }

    /**
     * @param Order $order
     * @throws \Exception
     */
    public function restoreCartFromOrder(Order $order)
    {
        $this->basketService->restoreBasket($order);
    }
}
