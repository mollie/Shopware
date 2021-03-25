<?php

namespace MollieShopware\Command;

use Doctrine\ORM\EntityNotFoundException;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KlarnaShippingCommand extends ShopwareCommand
{


    const LOG_PREFIX = 'CLI Klarna: ';

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


    private $countSuccess;
    private $countFailed;
    private $countSkipped;
    private $countRepaired;


    /**
     * @param Config $config
     * @param ModelManager $modelManager
     * @param MollieGatewayInterface $gwMollie
     * @param LoggerInterface $logger
     * @param null $name
     */
    public function __construct(Config $config, ModelManager $modelManager, MollieGatewayInterface $gwMollie, LoggerInterface $logger, $name = null)
    {
        parent::__construct($name);

        $this->config = $config;
        $this->gwMollie = $gwMollie;
        $this->logger = $logger;

        $this->statusValidator = new MollieStatusValidator();

        $this->repoShops = $modelManager->getRepository(Shop::class);
        $this->repoOrders = $modelManager->getRepository(Order::class);
        $this->repoTransactions = $modelManager->getRepository(Transaction::class);
    }

    /**
     *
     */
    public function configure()
    {
        $this
            ->setName('mollie:ship:klarna')
            ->setDescription('Ship completed Klarna orders');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Klarna Ship Command');
        $io->text('Searching for all non-shipped Klarna orders and mark them as shipped if the status is correct...');


        /** @var Transaction[] $transactions */
        $transactions = $this->repoTransactions->findBy(
            [
                'isShipped' => false,
                'paymentMethod' => 'mollie_' . PaymentMethod::KLARNA_PAY_LATER
            ]
        );


        if ($transactions === null || !is_array($transactions)) {
            $io->success("No Mollie Transactions found!");
            return;
        }


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

                /** @var Order|null $swOrder */
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

                    $this->logger->error(self::LOG_PREFIX . 'No Shop ID found for transaction: ' . $transaction->getId() . '!');

                    $this->countFailed++;
                    continue;
                }


                $shop = $this->repoShops->find($shopID);

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
                        self::LOG_PREFIX . 'Order ' . $mollieOrderID . ' not found in Mollie',
                        array(
                            'data' => array(
                                'shopId' => $shopID,
                            ))
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


                /** @var Order|null $order */
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

                    $this->logger->error(self::LOG_PREFIX . $ex->getMessage());

                    $this->countFailed++;
                    continue;
                }

            } catch (\Exception $e) {

                $this->countFailed++;

                $io->error($e->getMessage());

                $this->logger->error(self::LOG_PREFIX . $e->getMessage());
            }
        }

        $tableView->render();


        $io->section('Klarna Shipping command executed...');

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
            $io->error('Klarna Shipping failed');
        } else {
            $io->success('Klarna Shipping successful');
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

            /** @var Order|null $order */
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
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
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
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
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
                'Transaction has no OrderID in Shopware. Every Klarna order must have an order. Please verify this transaction in Shopware.',
                $shop->getName()
            )
        );

        $this->logger->error(self::LOG_PREFIX . 'No order ID found for transaction: ' . $transaction->getId() . ' in Shopware. Please verify your data!');

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
        /** @var Order|null $order */
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

        $this->logger->error(self::LOG_PREFIX . 'No order found for transaction: ' . $transaction->getId() . ' and orderID' . $transaction->getOrderId() . ' in Shopware. Please verify your data!');

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
        $notShippableStates = array(
            Status::PAYMENT_STATE_OPEN,
            Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED
        );

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
        if ($swOrder->getOrderStatus()->getId() === $this->config->getKlarnaShipOnStatus()) {
            return true;
        }

        $table->addRow(
            $this->buildRow(
                $transaction->getId(),
                $transaction->getOrderNumber(),
                $swOrder->getOrderStatus()->getName(),
                $swOrder->getPaymentStatus()->getName(),
                'This order status is not configured to trigger a shipping.',
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
        $mollieOrder->shipAll();

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
