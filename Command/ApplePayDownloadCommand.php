<?php

namespace MollieShopware\Command;

use MollieShopware\Components\ApplePayDirect\Services\ApplePayDomainFileDownloader;
use Psr\Log\LoggerInterface;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ApplePayDownloadCommand extends ShopwareCommand
{

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct();
    }


    /**
     * @return void
     */
    public function configure()
    {
        $this
            ->setName('mollie:applepay:download-verification')
            ->setDescription('Download the latest Apple Pay Domain Verification File of Mollie.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Apple Pay Domain Verification file download');

        $this->logger->info('Downloading new Apple Pay Domain Verification file from CLI command');

        $downloader = new ApplePayDomainFileDownloader();
        $downloader->downloadDomainAssociationFile(Shopware()->DocPath());

        $io->success('New Apple Pay Domain Verification file has been downloaded into your ./.well-known folder');
    }
}
