<?php

namespace MollieShopware\Command;

use MollieShopware\Components\Services\PaymentMethodService;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PaymentImportCommand extends ShopwareCommand
{

    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param PaymentMethodService $paymentMethodService
     * @param LoggerInterface $logger
     */
    public function __construct(PaymentMethodService $paymentMethodService, LoggerInterface $logger)
    {
        $this->paymentMethodService = $paymentMethodService;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('mollie:payments:import')
            ->setDescription('Imports and updates all Mollie payment methods');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Payment Methods Import');

        try {
            $this->logger->info('Starting payment methods import on CLI');

            $importCount = $this->paymentMethodService->installPaymentMethods(false);

            $this->logger->info($importCount . ' Payment Methods have been successfully imported on CLI');

            $io->success($importCount . ' Payment Methods have been updated successfully!');
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when importing payment methods on CLI',
                [
                    'error' => $e->getMessage(),
                ]
            );

            $io->error($e->getMessage());
        }
    }
}
