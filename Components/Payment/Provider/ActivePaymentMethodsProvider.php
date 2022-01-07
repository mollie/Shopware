<?php

namespace MollieShopware\Components\Payment\Provider;

use Exception;
use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Components\Config;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Payment\ActivePaymentMethodsProviderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Models\Shop\Shop;

class ActivePaymentMethodsProvider implements ActivePaymentMethodsProviderInterface
{
    /** @var Config */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Returns a collection of active payment methods from the Mollie API.
     *
     * @param array $parameters
     * @param Shop[]|null $shops
     *
     * @return array
     */
    public function getActivePaymentMethodsFromMollie($parameters = [], $shops = [])
    {
        $methods = [];

        if (empty($shops)) {
            return $methods;
        }

        foreach($shops as $shop) {
            try {
                $methods = $this->getActivePaymentMethodsForShop($shop, $methods, $parameters);
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf('Error when loading active payment methods from Mollie for shop %s', $shop->getName()),
                    [
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $methods;
    }

    /**
     * @param Shop $shop
     * @param array $methods
     * @param array $parameters
     * @return array
     * @throws ApiException
     */
    private function getActivePaymentMethodsForShop(Shop $shop, array $methods = [], array $parameters = [])
    {
        $mollieApiFactory = new MollieApiFactory($this->config, $this->logger);
        $mollieApiClient = $mollieApiFactory->create($shop->getId());

        if (!in_array('resource', $parameters, true)) {
            $parameters['resource'] = 'orders';
        }

        if (!in_array('includeWallets', $parameters, true)) {
            $parameters['includeWallets'] = 'applepay';
        }

        $activeMethods = $mollieApiClient->methods->allActive($parameters);

        foreach ($activeMethods->getArrayCopy() as $method) {
            $methodIsInArray = !empty(array_filter($methods, static function ($item) use ($method) {
                return $item->id === $method->id;
            }));

            if ($methodIsInArray) {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }
}
