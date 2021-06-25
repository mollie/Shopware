<?php

namespace MollieShopware\Command;

use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Facades\ShippingCommand\ShippingCommandFacade;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KlarnaShippingCommand extends ShopwareCommand
{

    /**
     *
     */
    const LOG_PREFIX = 'CLI Klarna: ';

    /**
     * @var ShippingCommandFacade
     */
    private $facade;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;


    /**
     * KlarnaShippingCommand constructor.
     * @param Config $config
     * @param ModelManager $modelManager
     * @param \Enlight_Template_Manager $smarty
     * @param MollieGatewayInterface $gwMollie
     * @param LoggerInterface $logger
     * @param null $name
     */
    public function __construct(Config $config, ModelManager $modelManager, \Enlight_Template_Manager $smarty, MollieGatewayInterface $gwMollie, LoggerInterface $logger, $name = null)
    {
        parent::__construct($name);

        $repoShops = $modelManager->getRepository(Shop::class);
        $repoOrders = $modelManager->getRepository(Order::class);
        $this->repoTransactions = $modelManager->getRepository(Transaction::class);

        $this->facade = new ShippingCommandFacade(
            self::LOG_PREFIX,
            $config,
            $gwMollie,
            new MollieShipping($gwMollie, $smarty),
            new MollieStatusValidator(),
            $logger,
            $repoShops,
            $repoOrders,
            $this->repoTransactions
        );
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
        $io->text('Searching for all non-shipped Klarna Pay Later orders and mark them as shipped if the status is correct...');

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

        $this->facade->ship(
            $transactions,
            $io,
            $output,
            $this->container
        );
    }

}
