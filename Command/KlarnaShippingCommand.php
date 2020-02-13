<?php

namespace MollieShopware\Command;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Config;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KlarnaShippingCommand extends ShopwareCommand
{
    /** @var Config */
    private $config;

    /** @var ModelManager */
    private $modelManager;

    /** @var MollieApiClient */
    private $apiClient;

    public function __construct(
        Config $config,
        ModelManager $modelManager,
        MollieApiClient $apiClient,
        $name = null
    )
    {
        parent::__construct($name);

        $this->config = $config;
        $this->modelManager = $modelManager;
        $this->apiClient = $apiClient;
    }

    public function configure()
    {
        $this
            ->setName('mollie:ship:klarna')
            ->setDescription('Ship completed Klarna orders');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Transaction[] $transactions */
        $transactions = null;

        /** @var TransactionRepository $transactionRepository */
        $transactionRepository = $this->modelManager
            ->getRepository(Transaction::class);

        if ($transactionRepository !== null) {
            $transactions = $transactionRepository->findBy([
                'isShipped' => false,
                'paymentMethod' => 'mollie_klarna'
            ]);
        }

        if (
            $transactions !== null
            && is_array($transactions)
        ) {
            $output->writeln(count($transactions) . ' orders to update.');

            foreach ($transactions as $transaction) {
                $output->writeln('Updating order ' . $transaction->getOrderNumber());

                /** @var Order $order */
                $order = null;

                if ($transaction->getOrderId() === null) {
                    continue;
                }

                /** @var Repository $orderRepository */
                $orderRepository = $this->modelManager->getRepository(Order::class);

                if ($orderRepository === null) {
                    continue;
                }

                /** @var Order $order */
                $order = $orderRepository->find($transaction->getOrderId());

                if ($order === null) {
                    continue;
                }

                // Ship order
                try {
                    $mollieOrder = $this->apiClient->orders->get($transaction->getMollieId());

                    if (
                        $mollieOrder !== null
                        && $order->getOrderStatus()->getId() === $this->config->getKlarnaShipOnStatus()
                        && !in_array($order->getPaymentStatus()->getId(), [
                            Status::PAYMENT_STATE_OPEN,
                            Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED
                        ], true)
                    ) {
                        $mollieOrder->shipAll();
                        $transaction->setIsShipped(true);
                    }
                } catch (\Exception $e) {
                    //
                }

                // Save order
                try {
                    $transactionRepository->save($transaction);
                } catch (\Exception $e) {
                    //
                }
            }

            $output->writeln('Done.');
        }
    }
}