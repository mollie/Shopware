<?php

// Mollie Shopware Plugin Version: 1.4.2

namespace MollieShopware\Commands\Mollie;

    use Shopware\Bundle\StoreFrontBundle\Struct\Shop;
    use Shopware\Commands\ShopwareCommand;

    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class GetPaymentMethodsCommand extends ShopwareCommand
    {

        /**
         * {@inheritdoc}
         */
        protected function configure()
        {
            $this
                ->setName('mollie:update_payment_methods')
                ->setDescription('Update payment methods offered by Mollie')
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