<?php

namespace MollieShopware\Command;

use InvalidArgumentException;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Facades\Refund\RefundOrderFacade;
use MollieShopware\Services\Refund\RefundInterface;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrdersRefundCommand extends ShopwareCommand
{

    /**
     * @var RefundOrderFacade
     */
    private $refundFacade;

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
        $this->logger = $logger;

        $this->refundFacade = new RefundOrderFacade(
            $refundService,
            $orderService
        );

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
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'The order number of the order, that should be refunded.')
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

        $orderNumber = (string)$input->getArgument('orderNumber');
        $customAmount = (float)$input->getArgument('customAmount');


        if (empty($orderNumber)) {
            throw new InvalidArgumentException('No orderNumber provided as argument!');
        }

        try {

            $this->logger->info('Starting Refund on CLI for Order: ' . $orderNumber . ', Amount: ' . $customAmount);

            $this->refundFacade->refundOrder($orderNumber, $customAmount);

            $this->logger->info('Refund Success on CLI for Order: ' . $orderNumber . ', Amount: ' . $customAmount);

            $io->success('Order ' . $orderNumber . ' was successfully refunded.');

        } catch (\Exception $e) {
            $this->logger->error(
                'Error when processing Refund for Order ' . $orderNumber . ' on CLI',
                [
                    'error' => $e->getMessage(),
                ]
            );

            $io->error($e->getMessage());
        }
    }


}
