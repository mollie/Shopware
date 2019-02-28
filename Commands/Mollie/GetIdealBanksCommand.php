<?php

// Mollie Shopware Plugin Version: 1.4.4

namespace MollieShopware\Commands\Mollie;

use Shopware\Bundle\StoreFrontBundle\Struct\Shop;
use Shopware\Commands\ShopwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetIdealBanksCommand extends ShopwareCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mollie:update_banks')
            ->setDescription('Update banks offered by Mollie iDeal')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Mollie</info>');
    }

}