<?php

namespace MollieShopware\Command;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Helpers\MollieShopSwitcher;
use MollieShopware\Components\Logger;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KlarnaShippingCommand extends ShopwareCommand
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(
        Config $config,
        ModelManager $modelManager,
        MollieApiClient $apiClient,
        LoggerInterface $logger,
        $name = null
    )
    {
        parent::__construct($name);

        $this->config = $config;
        $this->modelManager = $modelManager;
        $this->apiClient = $apiClient;
        $this->logger = $logger;
    }

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


        $notShippableStates = array(
            Status::PAYMENT_STATE_OPEN,
            Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED
        );


        /** @var Transaction[] $transactions */
        $transactions = null;

        /** @var TransactionRepository $transactionRepository */
        $transactionRepository = $this->modelManager->getRepository(Transaction::class);

        if ($transactionRepository !== null) {
            $transactions = $transactionRepository->findBy([
                'isShipped' => false,
                'paymentMethod' => 'mollie_' . PaymentMethod::KLARNA_PAY_LATER
            ]);
        }

        if ($transactions === null || !is_array($transactions)) {
            $io->success("No Mollie Transactions found!");
        }


        $countSuccess = 0;
        $countFailed = 0;
        $countSkipped = 0;
        $countRepaired = 0;

        if ($transactions !== null && is_array($transactions)) {

            $io->text('Found ' . count($transactions) . ' orders that should be processed.');

            $switcher = new MollieShopSwitcher($this->container);

            $tableView = new Table($output);
            $tableView->setHeaders(['order number', 'shipping status', 'error message', 'shop name']);

            /** @var Repository $orderRepository */
            $orderRepository = $this->modelManager->getRepository(Order::class);


            /** @var Transaction $transaction */
            foreach ($transactions as $transaction) {

                try {
                    /** @var Order|null $order */
                    $order = null;

                    if ($transaction->getOrderId() === null) {
                        $countFailed++;

                        $tableView->addRow([$transaction->getOrderNumber(), '', 'transaction does not have an order ID']);

                        $this->logger->error('Klarna Ship Command: No order ID found for transaction: ' . $transaction->getId() . ' in Shopware. Please verify your data!');

                        continue;
                    }

                    /** @var Order|null $order */
                    $order = $orderRepository->find($transaction->getOrderId());

                    if ($order === null) {
                        $countFailed++;

                        $tableView->addRow([$transaction->getOrderNumber(), '', 'transaction does not have an order in shopware']);

                        $this->logger->error('Klarna Ship Command: No order found for transaction: ' . $transaction->getId() . ' in Shopware. Please verify your data!');

                        continue;
                    }

                    # get the correct configuration
                    # from the sub shop of the current order
                    $this->config = $switcher->getConfig($order->getShop()->getId());
                    $this->apiClient = $switcher->getMollieApi($order->getShop()->getId());


                    # now request the order object from mollie
                    # this is the current entity in their system
                    $mollieOrder = $this->apiClient->orders->get($transaction->getMollieId());

                    if ($mollieOrder === null) {
                        $tableView->addRow([$transaction->getOrderNumber(), '', 'no order found in Mollie']);

                        $countFailed++;
                        continue;
                    }

                    # REPAIR ORDERS ALREADY SHIPPED ----------------------------------------------------------------
                    if ($mollieOrder->shipments()->count() > 0) {
                        $tableView->addRow([$transaction->getOrderNumber(), '', 'order is already shipped in Mollie', $order->getShop()->getName()]);

                        $transaction->setIsShipped(true);
                        $transactionRepository->save($transaction);

                        $countRepaired++;
                        continue;
                    }

                    # "CLOSE" FINALIZED ORDERS ----------------------------------------------------------------
                    if ($mollieOrder->isCanceled() || $mollieOrder->isExpired()) {
                        $tableView->addRow([$transaction->getOrderNumber(), '', 'order is cancelled or expired in Mollie']);

                        $transaction->setIsShipped(true);
                        $transactionRepository->save($transaction);

                        $countRepaired++;
                        continue;
                    }


                    # KEEP PENDING ORDERS "OPEN" ----------------------------------------------------------------
                    if (in_array($order->getPaymentStatus()->getId(), $notShippableStates, true)) {
                        $tableView->addRow([$transaction->getOrderNumber(), $order->getOrderStatus()->getName(), 'payment status invalid']);

                        $countSkipped++;
                        continue;
                    }

                    # KEEP ORDERS WITH OTHER STATUS "OPEN" ----------------------------------------------------------------
                    # we wait until the klarna shipping status is reached
                    if ($order->getOrderStatus()->getId() !== $this->config->getKlarnaShipOnStatus()) {
                        $tableView->addRow(
                            [
                                $transaction->getOrderNumber(),
                                $order->getOrderStatus()->getName(),
                                'order status is not at the configured shippable value',
                                $order->getShop()->getName()
                            ]
                        );

                        $countSkipped++;
                        continue;
                    }


                    $tableView->addRow([$transaction->getOrderNumber(), $order->getOrderStatus()->getName(), '', $order->getShop()->getName()]);
                    # finally ship our order
                    $mollieOrder->shipAll();

                    # mark it as "shipped" so that it isn't
                    # processed over and over again
                    $transaction->setIsShipped(true);
                    $transactionRepository->save($transaction);

                    $countSuccess++;

                } catch (\Exception $e) {
                    $countFailed++;
                    $io->error($e->getMessage());
                    $this->logger->error('Klarna Ship Command: ' . $e->getMessage());
                }
            }

            $tableView->render();
        }

        $io->section('Klarna Shipping command executed...');

        $io->table(
            ['Status', 'Orders'],
            [
                ['Successful Orders', $countSuccess],
                ['Failed Orders', $countFailed,],
                ['Skipped Orders', $countSkipped],
                ['Repaired Orders', $countRepaired],
            ]
        );

        $io->text('Some orders might be out-of-sync with Mollie.');
        $io->text('The repaired orders have been marked as shipped in Shopware because they are already shipped in Mollie.');
        $io->text("Thus, they won't be processed again the next time you run this command!");

        if ($countFailed > 0) {
            $io->error('Klarna Shipping failed');
        } else {
            $io->success('Klarna Shipping successful');
        }

    }
}