<?php

namespace MollieShopware\Command;

use InvalidArgumentException;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Services\Refund\RefundInterface;
use MollieShopware\Services\Refund\RefundService;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class OrdersRefundCommand extends ShopwareCommand
{

    /**
     * @var RefundInterface
     */
    private $refundService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param RefundInterface $refundService
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(RefundInterface $refundService, OrderService $orderService, LoggerInterface $logger)
    {
        $this->refundService = $refundService;
        $this->orderService = $orderService;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('mollie:orders:refund')
            ->setDescription('Perform full or partial refunds for a given order.')
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'The ordernumber of the order, that should be refunded.')
            ->addArgument('customAmount', null, 'Optional amount for partial refunds. Leave it empty for full refunds.', null);
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Order Refund');
        $io->text('Searching for order and refunding the given amount.');

        $orderNumber = $input->getArgument('orderNumber');
        $customAmount = $input->getArgument('customAmount');

        $this->validateInputArguments($orderNumber, $customAmount);

        $this->logger->info('Starting Refund on CLI for Order: ' . $orderNumber . ', Amount: ' . $customAmount);

        try {

            $transaction = $this->orderService->getOrderTransactionByNumber($orderNumber);

            $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

            if (empty($customAmount)) {
                $this->refundService->refundFullOrder($order, $transaction);
            } else {
                $this->refundService->refundPartialOrderAmount($order, $transaction, $customAmount);
            }


            $this->logger->info('Refund Success on CLI for Order: ' . $orderNumber . ', Amount: ' . $customAmount);

            $io->success('Order ' . $orderNumber . ' was successfully refunded.');

        } catch (\Exception $e) {

            $this->logger->error(
                'Error when processing Refund for Order ' . $orderNumber . ' on CLI',
                array(
                    'error' => $e->getMessage(),
                )
            );

            $io->error($e->getMessage());
        }
    }

    /**
     * @param string|array $orderNumber
     * @param string|array|null $refundAmount
     *
     * @throws InvalidArgumentException
     */
    private function validateInputArguments($orderNumber, $refundAmount)
    {
        if (\is_array($orderNumber) ||
            (
                $refundAmount !== null &&
                \is_array($refundAmount) &&
                !\is_numeric($refundAmount)
            )
        ) {
            throw new InvalidArgumentException(
                'There was an error during the input of information. Please only submit one orderNumber per execution and set refund amounts to be split with a dot.',
                1
            );
        }
    }

}