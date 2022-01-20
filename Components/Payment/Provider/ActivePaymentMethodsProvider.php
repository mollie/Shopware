<?php

namespace MollieShopware\Components\Payment\Provider;

use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Method;
use MollieShopware\Components\Basket\BasketAmount;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Payment\ActivePaymentMethodsProviderInterface;
use MollieShopware\Services\Mollie\Payments\Formatters\NumberFormatter;
use Psr\Log\LoggerInterface;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActivePaymentMethodsProvider implements ActivePaymentMethodsProviderInterface
{
    /** @var ContainerInterface */
    private $container;

    /** @var NumberFormatter */
    private $formatter;

    /** @var LoggerInterface */
    private $logger;

    /** @var MollieApiFactory */
    private $mollieApiFactory;

    public function __construct(ContainerInterface $container, MollieApiFactory $mollieApiFactory, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->formatter = new NumberFormatter();
        $this->mollieApiFactory = $mollieApiFactory;
        $this->logger = $logger;
    }

    /**
     * Returns an array of active payment methods from the Mollie API.
     *
     * @param array $parameters
     * @param array<Shop> $shops
     *
     * @return array<Method>
     */
    public function getActivePaymentMethods(array $parameters = [], array $shops = [])
    {
        $methods = [];

        # if no shops are provided, we try to get the current shop from the DI container
        if (empty($shops)) {
            $shop = $this->container->get('shop');

            if ($shop instanceof Shop) {
                $shops[] = $shop;
            }
        }

        # if still no shops are provided, we return an empty array
        if (empty($shops)) {
            return [];
        }

        # we loop over the array of shops to get active payment methods for every shop
        foreach($shops as $shop) {
            try {
                $methods = $this->getActivePaymentMethodsForShop($shop, $parameters);
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf('Error when loading active payment methods from Mollie for shop %s', $shop->getName()),
                    [
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        # we return an array of unique active payment methods
        return array_unique($methods);
    }

    /**
     * Returns an array of active payment methods for all shops for a specified amount and currency.
     *
     * @param BasketAmount $basketAmount
     * @param array $shops
     *
     * @return array<Method>
     */
    public function getActivePaymentMethodsForAmount(BasketAmount $basketAmount, array $shops = [])
    {
        # we return an array of payment methods available for a specified amount and currency
        return $this->getActivePaymentMethods([
            'amount' => [
                'value' => $this->formatter->formatNumber($basketAmount->getAmount()),
                'currency' => strtoupper($basketAmount->getCurrency()),
            ]
        ], $shops);
    }

    /**
     * Returns an array of active payment methods for a subshop.
     *
     * @param Shop $shop
     * @param array $parameters
     *
     * @return array<Method>
     *
     * @throws ApiException
     */
    private function getActivePaymentMethodsForShop(Shop $shop, array $parameters = [])
    {
        $mollieApiClient = $this->mollieApiFactory->create($shop->getId());

        # adds resource to parameters if not in array
        if (!in_array('resource', $parameters, true)) {
            $parameters['resource'] = 'orders';
        }

        # adds wallets to parameters if not in array
        if (!in_array('includeWallets', $parameters, true)) {
            $parameters['includeWallets'] = 'applepay';
        }

        return $mollieApiClient->methods
            ->allActive($parameters)
            ->getArrayCopy();
    }
}
