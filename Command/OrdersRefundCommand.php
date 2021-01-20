<?php

namespace MollieShopware\Command;

use Doctrine\ORM\EntityManager;
use Exception;
use InvalidArgumentException;
use MollieShopware\Models\Transaction;
use MollieShopware\Services\RefundService;
use MollieShopware\Traits\MollieApiClientTrait;
use Shopware\Commands\ShopwareCommand;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrdersRefundCommand extends ShopwareCommand
{
    use MollieApiClientTrait;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var RefundService
     */
    private $refundService;

    public function __construct(EntityManager $entityManager, RefundService $refundService)
    {
        $this->entityManager = $entityManager;
        $this->refundService = $refundService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('mollie:orders:refund')
            ->setDescription('Perform refunds for given order with optional given amount.')
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'The ordernumber of the order, which should be refunded.')
            ->addArgument('customAmount', null, 'the amount that shall be refunded.', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Order Refund');
        $io->text('Searching for order and refunding the given amount.');

        $orderNumber = $input->getArgument('orderNumber');
        $customAmount = $input->getArgument('customAmount');

        $this->validateInputArguments($orderNumber, $customAmount);

        /** @var Repository $transactionRepository */
        $transactionRepository = $this->entityManager->getRepository(Transaction::class);
        $orderRepository = $this->entityManager->getRepository(Order::class);

        /** @var Transaction $transactions */
        $transaction = $transactionRepository->findOneBy([
            'orderNumber' => $orderNumber
        ]);
        $order = $orderRepository->findOneBy([
            'number' => $orderNumber
        ]);

        if ($transaction === null) {
            $io->error('No order with the given order number was found!');

            return 1;
        }

        try {
            $this->refundService->refundOrderAmount($order, $transaction, $customAmount);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->success('The order was successfully refunded.');
    }

    /**
     * @param string|array $orderNumber
     * @param string|array|null $refundAmount
     *
     * @throws InvalidArgumentException
     */
    private function validateInputArguments($orderNumber, $refundAmount)
    {
        if(\is_array($orderNumber) ||
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