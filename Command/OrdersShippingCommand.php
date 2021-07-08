<?php

namespace MollieShopware\Command;

use InvalidArgumentException;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Gateways\Mollie\MollieGatewayFactory;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrdersShippingCommand extends ShopwareCommand
{

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var MollieGatewayFactory
     */
    private $mollieGatewayFactory;

    /**
     * @var \Smarty
     */
    private $template;

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
        $this->orderService = $orderService;
        $this->mollieGatewayFactory = $gatewayFactory;
        $this->template = $template;
        $this->logger = $logger;

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

        $isPartial = ($articleNumber !== null);


        if ($orderNumber === null) {
            throw new InvalidArgumentException('Missing Argument for Order Number!');
        }


        try {

            if ($isPartial) {
                $this->logger->info('Starting partial shipment on CLI for Order: ' . $orderNumber . ', Article: ' . $articleNumber);
            } else {
                $this->logger->info('Starting full shipment on CLI for Order: ' . $orderNumber);
            }


            $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

            if (!$order instanceof Order) {
                throw new OrderNotFoundException('Order with number: ' . $orderNumber . ' has not been found in Shopware!');
            }


            $mollieId = $this->orderService->getMollieOrderId($order);


            # create our mollie gateway
            # with the configuration from the shop of our order
            $mollieGateway = $this->mollieGatewayFactory->createForShop($order->getShop()->getId());

            # now retrieve the mollie order object
            # from our gateway
            $mollieOrder = $mollieGateway->getOrder($mollieId);


            $shipping = new MollieShipping($mollieGateway, $this->template);

            # now either perform a full shipment
            # or only a partial shipment for our order and its articles
            if (!$isPartial) {

                $shipping->shipOrder($order, $mollieOrder);

            } else {

                $detail = $this->searchArticleItem($order, $articleNumber);

                # if we did not provide a quantity
                # then we use the full quantity that has been ordered
                if ($shipQuantity === null) {
                    $shipQuantity = $detail->getQuantity();
                }

                $shipping->shipOrderPartially(
                    $order,
                    $mollieOrder,
                    $detail->getId(),
                    $shipQuantity
                );
            }


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

    /**
     * @param Order $order
     * @param string $articleNumber
     * @return Detail
     * @throws \Exception
     */
    private function searchArticleItem(Order $order, $articleNumber)
    {
        /** @var Detail $detail */
        foreach ($order->getDetails() as $detail) {

            if ($detail->getArticleNumber() === $articleNumber) {
                return $detail;
            }
        }

        throw new \Exception('Item with article number: ' . $articleNumber . ' not found in this order!');
    }

}
