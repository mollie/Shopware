<?php

namespace MollieShopware\Components\Payment\Provider;

use Exception;
use MollieShopware\Components\Config;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Payment\ActivePaymentMethodsProviderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Models\Shop\DetachedShop;

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
     * @param string|null $value
     * @param string|null $currency
     * @param DetachedShop[]|null $shops
     *
     * @return array
     */
    public function getActivePaymentMethodsFromMollie($value = '', $currency = '', $shops = [])
    {
        $methods = [];
        $mollieApiFactory = new MollieApiFactory($this->config, $this->logger);

        if (empty($shops)) {
            return $methods;
        }

        foreach($shops as $shop) {
            try {
                $mollieApiClient = $mollieApiFactory->create($shop->getId());

                $params = [
                    'resource' => 'orders',
                    'includeWallets' => 'applepay',
                ];

                if ($value !== '' && $currency !== '') {
                    $params['amount'] = [
                        'value' => (string) $value,
                        'currency' => $currency,
                    ];
                }

                $activeMethods = $mollieApiClient->methods->allActive($params);

                foreach ($activeMethods->getArrayCopy() as $method) {
                    $methodIsInArray = !empty(array_filter($methods, static function ($item) use ($method) {
                        return $item->id === $method->id;
                    }));

                    if ($methodIsInArray) {
                        continue;
                    }

                    $methods[] = $method;
                }
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
}
