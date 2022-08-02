<?php

namespace MollieShopware\Facades\Shipping;

use Doctrine\ORM\EntityNotFoundException;
use MollieShopware\Components\Config;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShippingCommandFacade
{

    /**
     * @var
     */
    private $LOG_PREFIX;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var MollieStatusValidator
     */
    private $statusValidator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Shopware\Models\Shop\Repository
     */
    private $repoShops;

    /**
     * @var Repository
     */
    private $repoOrders;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var MollieShipping
     */
    private $mollieShipping;

    /**
     * @var int
     */
    private $countSuccess;

    /**
     * @var int
     */
    private $countFailed;

    /**
     * @var int
     */
    private $countSkipped;

    /**
     * @var int
     */
    private $countRepaired;


    /**
     * @param $LOG_PREFIX
     * @param Config $config
     * @param MollieGatewayInterface $gwMollie
     * @param MollieShipping $mollieShipping
     * @param MollieStatusValidator $statusValidator
     * @param LoggerInterface $logger
     * @param \Shopware\Models\Shop\Repository $repoShops
     * @param Repository $repoOrders
     * @param TransactionRepository $repoTransactions
     */
    public function __construct($LOG_PREFIX, Config $config, MollieGatewayInterface $gwMollie, MollieShipping $mollieShipping, MollieStatusValidator $statusValidator, LoggerInterface $logger, \Shopware\Models\Shop\Repository $repoShops, Repository $repoOrders, TransactionRepository $repoTransactions)
    {
        $this->LOG_PREFIX = $LOG_PREFIX;

        $this->config = $config;
        $this->gwMollie = $gwMollie;
        $this->mollieShipping = $mollieShipping;
        $this->statusValidator = $statusValidator;
        $this->logger = $logger;
        $this->repoShops = $repoShops;
        $this->repoOrders = $repoOrders;
        $this->repoTransactions = $repoTransactions;
    }


    /**
     * @param array $transactions
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param $container
     */
    public function ship(array $transactions, SymfonyStyle $io, OutputInterface $output, $container)
    {
        $this->container = $container;

        $this->countSuccess = 0;
        $this->countFailed = 0;
        $this->countSkipped = 0;
        $this->countRepaired = 0;


        $io->text('Found ' . count($transactions) . ' orders that should be processed.');

        $tableView = new Table($output);
        $tableView->setHeaders($this->buildHeadline());


        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            try {

                /** @var null|Order $swOrder */
                $swOrder = null;

                $shopID = $transaction->getShopId();

                if ($shopID <= 0) {

                    # search for the shop ID in the connected
                    # order of the transaction (if existing)
                    $shopID = $this->getShopIdOfOrder($transaction);

                    if ($shopID > 0) {
                        # now repair the transaction
                        $transaction->setShopId($shopID);
                        $this->repoTransactions->save($transaction);
                    }
                }

                # -------------------------------------------------------------------

                if ($shopID <= 0) {
                    $tableView->addRow(
                        $this->buildRow(
                            $transaction->getId(),
                            '-',
                            '-',
                            '-',
                            'No Order and thus no shop found for this transaction! Please verify this data entry!',
                            '-'
                        )
                    );

                    $this->logger->error($this->LOG_PREFIX . 'No Shop ID found for transaction: ' . $transaction->getId() . '!');

                    $this->countFailed++;
                    continue;
                }


                $shop = $this->repoShops->find($shopID);


                if ($shop === null) {
                    $this->logger->error($this->LOG_PREFIX . 'No Shop found for ID: ' . $shopID . '!');
                    $this->countFailed++;
                    continue;
                }


                $this->switchApiToShop($shopID);

                $mollieOrderID = $transaction->getMollieOrderId();

                /** @var \Mollie\Api\Resources\Order $mollieOrder */
                $mollieOrder = null;

                try {

                    # now that we know in what shop it has been ordered (and thus, what API key we need),
                    # we can try to fetch the order from the Mollie API
                    $mollieOrder = $this->gwMollie->getOrder($mollieOrderID);
                } catch (\Exception $ex) {
                    # "CLOSE" ORDERS THAT DO NOT EXIST IN MOLLIE ----------------------------------------------------------------
                    # if mollie does not contain that order
                    # make sure to show an error
                    $tableView->addRow(
                        $this->buildRow(
                            $transaction->getId(),
                            '-',
                            '-',
                            '-',
                            'No Order found in Mollie for ID: ' . $mollieOrderID,
                            $shop->getName()
                        )
                    );

                    $this->logger->error(
                        $this->LOG_PREFIX . 'Order ' . $mollieOrderID . ' not found in Mollie',
                        [
                            'data' => [
                                'shopId' => $shopID,
                            ]]
                    );

                    $this->countFailed++;
                    continue;
                }


                # REPAIR ORDERS ALREADY SHIPPED ----------------------------------------------------------------
                if ($this->isAlreadyShipped($transaction, $mollieOrder, $tableView, $shop)) {
                    continue;
                }

                # "CLOSE" INVALID ORDERS ----------------------------------------------------------------
                # now check if the order from mollie is actually valid
                # if it was never authorized, paid or valid at all, then it was never completed
                # and thus needs to be marked as "processed" to avoid that it gets used in here over and over again.
                if (!$this->isOrderSuccessful($transaction, $mollieOrder, $tableView, $shop)) {
                    continue;
                }

                # CHECK DATA INTEGRITY WITH ORDERS ----------------------------------------------------------------
                # these are bad errors.
                # it means that our mollie order is completely valid and paid, and also NOT yet shipped
                # but somehow we do not have a linked or existing order in shopware!
                if (!$this->hasTransactionOrderID($transaction, $mollieOrder, $tableView, $shop)) {
                    continue;
                }

                if (!$this->hasTransactionOrder($transaction, $mollieOrder, $tableView, $shop)) {
                    continue;
                }


                /** @var null|Order $order */
                $swOrder = $this->repoOrders->find($transaction->getOrderId());


                # VERIFY PAYMENT STATUS ----------------------------------------------------------------
                # now that we have our shopware order, make sure to keep it untouched
                # if the payment status is in the list of our NOT_SHIPPABLE states.
                if (!$this->isShippablePaymentStatus($transaction, $swOrder, $tableView, $shop)) {
                    continue;
                }

                # VERIFY ORDER STATUS ----------------------------------------------------------------
                # this checks our target order status from the plugin configuration
                # if this is not the one where we trigger a shipping then just continue
                if (!$this->isShippableOrderStatus($transaction, $swOrder, $tableView, $shop)) {
                    continue;
                }


                try {
                    $this->shipOrder(
                        $transaction,
                        $mollieOrder,
                        $swOrder,
                        $tableView,
                        $shop
                    );
                } catch (\Exception $ex) {
                    $tableView->addRow(
                        $this->buildRow(
                            $transaction->getId(),
                            $transaction->getOrderNumber(),
                            $swOrder->getOrderStatus()->getName(),
                            $swOrder->getPaymentStatus()->getName(),
                            'Failed: ' . $ex->getMessage(),
                            $shop->getName()
                        )
                    );

                    $this->logger->error($this->LOG_PREFIX . $ex->getMessage());

                    $this->countFailed++;
                    continue;
                }
            } catch (\Exception $e) {
                $this->countFailed++;

                $io->error($e->getMessage());

                $this->logger->error($this->LOG_PREFIX . $e->getMessage());
            }
        }

        $tableView->render();


        $io->section('Shipping command executed...');

        $io->table(
            ['Status', 'Orders'],
            [
                ['Successful Orders', $this->countSuccess],
                ['Failed Orders', $this->countFailed,],
                ['Skipped Orders', $this->countSkipped],
                ['Repaired Orders', $this->countRepaired],
            ]
        );

        $io->text('Some orders might be out-of-sync with Mollie.');
        $io->text('The repaired orders have been marked as shipped in Shopware because they are already shipped in Mollie.');
        $io->text("Thus, they won't be processed again the next time you run this command!");

        if ($this->countFailed > 0) {
            $io->error('Shipping failed');
        } else {
            $io->success('Shipping successful');
        }
    }

    /**
     * @param $shopId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    private function switchApiToShop($shopId)
    {
        $switcher = new MollieShopSwitcher($this->container);

        $this->config = $switcher->getConfig($shopId);

        $clientForShop = $switcher->getMollieApi($shopId);

        $this->gwMollie->switchClient($clientForShop);
    }

    /**
     * @param Transaction $transaction
     * @return int
     */
    private function getShopIdOfOrder(Transaction $transaction)
    {
        if ($transaction->getOrderId() === null) {
            return 0;
        }

        try {

            /** @var null|Order $order */
            $order = $this->repoOrders->find($transaction->getOrderId());

            return $order->getShop()->getId();
        } catch (EntityNotFoundException $ex) {
            return 0;
        }
    }


    /**
     * @param Transaction $transaction
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Table $table
     * @param Shop $shop
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @return bool
     */
    private function isAlreadyShipped(Transaction $transaction, \Mollie\Api\Resources\Order $mollieOrder, Table $table, Shop $shop)
    {
        if ($mollieOrder->shipments()->count() <= 0) {
            return false;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                '-',
                '-',
                '-',
                'Transaction: ' . $mollieOrder->id . ' already shipped. Repair it and mark it as "processed".',
                $shop->getName()
            )
        );

        $transaction->setIsShipped(true);
        $this->repoTransactions->save($transaction);

        $this->countRepaired++;

        return true;
    }

    /**
     * @param Transaction $transaction
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Table $table
     * @param Shop $shop
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @return bool
     */
    private function isOrderSuccessful(Transaction $transaction, \Mollie\Api\Resources\Order $mollieOrder, Table $table, Shop $shop)
    {
        $isOrderSuccessful = $this->statusValidator->didOrderCheckoutSucceed($mollieOrder);

        if ($isOrderSuccessful) {
            return true;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                '-',
                '-',
                '-',
                'Order was never successful. Mark it as "processed".',
                $shop->getName()
            )
        );

        $transaction->setIsShipped(true);
        $this->repoTransactions->save($transaction);

        $this->countRepaired++;

        return false;
    }

    /**
     * @param Transaction $transaction
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Table $table
     * @param Shop $shop
     * @return bool
     */
    private function hasTransactionOrderID(Transaction $transaction, \Mollie\Api\Resources\Order $mollieOrder, Table $table, Shop $shop)
    {
        if ($transaction->getOrderId() !== null) {
            return true;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                '-',
                '-',
                '-',
                'Transaction has no OrderID in Shopware. Every order must have an order. Please verify this transaction in Shopware.',
                $shop->getName()
            )
        );

        $this->logger->error($this->LOG_PREFIX . 'No order ID found for transaction: ' . $transaction->getId() . ' in Shopware. Please verify your data!');

        $this->countFailed++;

        return false;
    }

    /**
     * @param Transaction $transaction
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Table $table
     * @param Shop $shop
     * @return bool
     */
    private function hasTransactionOrder(Transaction $transaction, \Mollie\Api\Resources\Order $mollieOrder, Table $table, Shop $shop)
    {
        /** @var null|Order $order */
        $swOrder = $this->repoOrders->find($transaction->getOrderId());

        if ($swOrder !== null) {
            return true;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                '-',
                '-',
                '-',
                'Linked order with ID ' . $transaction->getOrderId() . ' does not exist in Shopware. Please verify this in Shopware.',
                $shop->getName()
            )
        );

        $this->logger->error($this->LOG_PREFIX . 'No order found for transaction: ' . $transaction->getId() . ' and orderID' . $transaction->getOrderId() . ' in Shopware. Please verify your data!');

        $this->countFailed++;

        return false;
    }


    /**
     * @param Transaction $transaction
     * @param Order $swOrder
     * @param Table $table
     * @param Shop $shop
     * @return bool
     */
    private function isShippablePaymentStatus(Transaction $transaction, Order $swOrder, Table $table, Shop $shop)
    {
        $notShippableStates = [
            Status::PAYMENT_STATE_OPEN,
            Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED
        ];

        if (!in_array($swOrder->getPaymentStatus()->getId(), $notShippableStates, true)) {
            return true;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                $swOrder->getNumber(),
                $swOrder->getOrderStatus()->getName(),
                $swOrder->getPaymentStatus()->getName(),
                'Payment Status is not allowed for shipping.',
                $shop->getName()
            )
        );

        $this->countSkipped++;

        return false;
    }

    /**
     * @param Transaction $transaction
     * @param Order $swOrder
     * @param Table $table
     * @param Shop $shop
     * @return bool
     */
    private function isShippableOrderStatus(Transaction $transaction, Order $swOrder, Table $table, Shop $shop)
    {
        $requiredStatusId = $this->config->getOrdersShipOnStatus();

        if ($swOrder->getOrderStatus()->getId() === $requiredStatusId) {
            return true;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                $transaction->getOrderNumber(),
                '(' . $swOrder->getOrderStatus()->getId() . ') ' . $swOrder->getOrderStatus()->getName(),
                $swOrder->getPaymentStatus()->getName(),
                'This order status is not ready to be shipped. Required Status: ' . $requiredStatusId,
                $shop->getName()
            )
        );

        $this->countSkipped++;

        return false;
    }

    /**
     * @param Transaction $transaction
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Order $swOrder
     * @param Table $table
     * @param Shop $shop
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function shipOrder(Transaction $transaction, \Mollie\Api\Resources\Order $mollieOrder, Order $swOrder, Table $table, Shop $shop)
    {
        # finally ship our order
        $this->mollieShipping->shipOrder($swOrder, $mollieOrder);

        # mark it as "shipped" so that it isn't
        # processed over and over again
        $transaction->setIsShipped(true);
        $this->repoTransactions->save($transaction);

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                $transaction->getOrderNumber(),
                $swOrder->getOrderStatus()->getName(),
                $swOrder->getPaymentStatus()->getName(),
                'Shipping instructions have been successfully sent to Mollie.',
                $shop->getName()
            )
        );

        $this->countSuccess++;
    }

    /**
     * @return array
     */
    private function buildHeadline()
    {
        return [
            'Transaction',
            'Shop',
            'Order',
            'Order Status',
            'Payment Status',
            'Error',
        ];
    }

    /**
     * @param $transactionId
     * @param $orderNumber
     * @param $orderStatus
     * @param $paymentStatus
     * @param $message
     * @param $shop
     * @return array
     */
    private function buildRow($transactionId, $orderNumber, $orderStatus, $paymentStatus, $message, $shop)
    {
        return [
            $transactionId,
            $shop,
            $orderNumber,
            $orderStatus,
            $paymentStatus,
            $message,
        ];
    }
}
