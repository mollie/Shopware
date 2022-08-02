<?php

namespace MollieShopware\Command;

use Doctrine\ORM\EntityManager;
use MollieShopware\Components\Config;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Facades\Shipping\ShippingCommandFacade;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShippingCommand extends ShopwareCommand
{

    /**
     *
     */
    const LOG_PREFIX = 'CLI Shipping: ';

    /**
     * @var ShippingCommandFacade
     */
    private $facade;

    /**
     * @var EntityManager
     */
    private $entityManager;


    /**
     * ShippingCommand constructor.
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

        $this->entityManager = $modelManager;

        $repoShops = $modelManager->getRepository(Shop::class);
        $repoOrders = $modelManager->getRepository(Order::class);
        $repoTransactions = $modelManager->getRepository(Transaction::class);

        $this->facade = new ShippingCommandFacade(
            self::LOG_PREFIX,
            $config,
            $gwMollie,
            new MollieShipping($gwMollie, $smarty),
            new MollieStatusValidator(),
            $logger,
            $repoShops,
            $repoOrders,
            $repoTransactions
        );
    }

    /**
     *
     */
    public function configure()
    {
        $this
            ->setName('mollie:ship:orders')
            ->setDescription('Ship completed orders (All Payment Methods)');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return null|int|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Shipping Command');
        $io->text('Searching for all non-shipped orders and mark them as shipped if the status is correct...');


        $qb = $this->entityManager->createQueryBuilder();

        /** @var Transaction[] $transactions */
        $transactions = $qb->select('t')
            ->from(Transaction::class, 't')
            ->where($qb->expr()->like('t.mollieId', ':mollieId'))
            ->andWhere($qb->expr()->eq('t.isShipped', ':shipped'))
            ->setParameter(':mollieId', 'ord_%')
            ->setParameter(':shipped', false)
            ->getQuery()
            ->getResult();

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
