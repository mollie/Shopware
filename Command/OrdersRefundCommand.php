<?php

namespace MollieShopware\Command;

use Doctrine\ORM\EntityManager;
use Exception;
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

        if (
            \is_array($orderNumber) ||
            (
                $customAmount !== null &&
                \is_array($customAmount) &&
                !\is_numeric($customAmount)
            )
        ) {
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
            'number' => $orderNumber
        ]);

        if ($transaction === null) {
            $io->error('No order with the given order number was found!');

            return 1;
        }

        $mollieClient = $this->getMollieApi($order->getShop()->getId());

        if ($mollieClient === null) {
            $io->error('Something went wrong trying to get an API Client Instance. Please try again later.');

            return 1;
        }

        try {
            if ($transaction->getMolliePaymentId() !== null) {
                $molliePayment = $mollieClient->payments->get($transaction->getMolliePaymentId());

                if (
                    (
                        $customAmount === null ||
                        $molliePayment->getAmountRemaining() < $customAmount
                    ) && (
                        $molliePayment->canBeRefunded() ||
                        $molliePayment->canBePartiallyRefunded()
                    )
                ) {
                    $this->refundService->refundPayment($order, $molliePayment);
                }

                if ($customAmount !== null && $molliePayment->canBePartiallyRefunded() && $molliePayment->getAmountRemaining() > $customAmount) {
                    $this->refundService->partialRefundPayment($order, $molliePayment, $customAmount);
                }
            }

            if ($transaction->getMollieOrderId() !== null) {
                $mollieOrder = $mollieClient->orders->get($transaction->getMollieOrderId());

                if ($customAmount === null || $mollieOrder->amountCaptured <= $customAmount) {
                    $this->refundService->refundOrder($order, $mollieOrder);
                }

                if ($customAmount !== null && $mollieOrder->amountCaptured > $customAmount) {
                    // TODO: Implement partial return on the CLI. This is currently not doable, since you need to refund based on a lineitem.
                    throw new Exception('partial refunds of orders is currently not implemented.');
                }
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->success('The order was successfully refunded.');

        return 0;
    }
}