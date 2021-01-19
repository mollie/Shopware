<?php

namespace MollieShopware\Command;

use Doctrine\Common\Util\Debug;
use Doctrine\ORM\EntityManager;
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
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'The ordernumber of the order, which should be refunded.')
            ->addArgument('customAmount', InputArgument::REQUIRED, 'the amount that shall be refunded.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Order Refund');
        $io->text('Searching for order and refunding the given amount.');

        $orderNumber = $input->getArgument('orderNumber');
        $customAmount = $input->getArgument('customAmount');

        if (\is_array($orderNumber) || \is_array($customAmount) || !\is_numeric($customAmount)) {
            $io->error('There was an error during the input of information. Please only submit one orderNumber per execution and set refund amounts to be split with a dot.');

            return 1;
        }

        /** @var Repository $transactionRepository */
        $transactionRepository = $this->entityManager->getRepository(Transaction::class);
        $orderRepository = $this->entityManager->getRepository(Order::class);

        /** @var array|Transaction[] $transactions */
        $transaction = $transactionRepository->findOneBy([
            'orderNumber' => $orderNumber
        ]);
        $order = $orderRepository->findOneBy([
            'orderNumber' => $orderNumber
        ]);

        if ($transaction === null) {
            $io->error('No order with the given order number was found!');

            return 1;
        }

        $mollieClient = $this->getMollieApi($transaction->getCustomer()->getShop()->getId());

        if ($mollieClient === null) {
            $io->error('Something went wrong trying to get an API Client Instance. Please try again later.');

            return 1;
        }

        if ($transaction->getMolliePaymentId() !== '') {
            $molliePayment = $mollieClient->payments->get($transaction->getMolliePaymentId());

            if ($molliePayment->getAmountRemaining() < $customAmount && ($molliePayment->canBeRefunded() || $molliePayment->canBePartiallyRefunded())) {
                $this->refundService->refundPayment($order, $molliePayment);
            }

            if ($molliePayment->canBePartiallyRefunded() && $molliePayment->getAmountRemaining() < $customAmount) {
                $this->refundService->partialRefundPayment($order, $molliePayment, $customAmount);
            }
        }

//        if ($transaction->getMollieOrderId() !== '') {
//            $mollieOrder = $mollieClient->orders->get($transaction->getMollieOrderId());
//
//            $this->refundService->partialRefundOrder($order, $order->getDetails()->first(), $mollieOrder, $mollieOrder->lines[0]);
//        }

        return 0;
    }
}