<?php

namespace MollieShopware\Command;

use InvalidArgumentException;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Facades\Shipping\ShipOrderFacade;
use MollieShopware\Gateways\Mollie\MollieGatewayFactory;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrdersShippingCommand extends ShopwareCommand
{

    /**
     * @var ShipOrderFacade
     */
    private $shipOrderFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * OrdersShippingCommand constructor.
     * @param OrderService $orderService
     * @param MollieGatewayFactory $gatewayFactory
     * @param \Smarty $template
     * @param LoggerInterface $logger
     */
    public function __construct(OrderService $orderService, MollieGatewayFactory $gatewayFactory, \Smarty $template, LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->shipOrderFacade = new ShipOrderFacade(
            $orderService,
            $gatewayFactory,
            $template
        );

        parent::__construct();
    }

    /**
     * @return void
     */
    public function configure()
    {
        $this
            ->setName('mollie:orders:ship')
            ->setDescription('Perform full or partial shipment for a given order.')
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'The order number of the order, that should be shipped.')
            ->addArgument('articleNumber', null, 'Optional article number for partial shipment. Leave it empty to ship all positions of that order.', null)
            ->addArgument('quantity', null, 'Quantity for the provided article. Leave it empty to ship all quantities of the article.', null);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Order Shipment');
        $io->text('Full or partial shipment of the provided order');


        /** @var null|string $orderNumber */
        $orderNumber = $input->getArgument('orderNumber');
        /** @var null|string $articleNumber */
        $articleNumber = $input->getArgument('articleNumber');
        /** @var null|int $shipQuantity */
        $shipQuantity = $input->getArgument('quantity');

        if ($orderNumber === null) {
            throw new InvalidArgumentException('Missing Argument for Order Number!');
        }


        try {
            $isPartial = ($articleNumber !== null);

            if ($isPartial) {
                $this->logger->info('Starting partial shipment on CLI for Order: ' . $orderNumber . ', Article: ' . $articleNumber);
            } else {
                $this->logger->info('Starting full shipment on CLI for Order: ' . $orderNumber);
            }

            $this->shipOrderFacade->shipOrder(
                $orderNumber,
                $articleNumber,
                $shipQuantity
            );

            if ($isPartial) {
                $this->logger->info('Partial Shipping Success on CLI for Order: ' . $orderNumber . ', Article: ' . $articleNumber);
            } else {
                $this->logger->info('Shipping Success on CLI for Order: ' . $orderNumber);
            }

            $io->success('Order ' . $orderNumber . ' was successfully shipped.');
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when processing shipment for Order ' . $orderNumber . ' on CLI',
                [
                    'error' => $e->getMessage(),
                ]
            );

            $io->error($e->getMessage());
        }
    }
}
