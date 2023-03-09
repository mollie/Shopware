<?php

namespace MollieShopware\Components\Services;

use Shopware\Components\DependencyInjection\Container as DIContainer;

class ShopwareVersionService
{

    /**
     * @var string
     */
    private $shopwareVersion;


    /**
     * @param DIContainer $container
     */
    public function __construct($container)
    {
        $releaseKey = 'shopware.release.version';

        if ($container->hasParameter($releaseKey)) {
            $this->shopwareVersion = (string)$container->getParameter($releaseKey);
        } else {
            # coming with v5.3 as far as I know
            # we need it only for checks > 5.7.x, so I guess that should be fine
            $this->shopwareVersion = '5.3';
        }
    }

    /**
     * @return string
     */
    public function getShopwareVersion()
    {
        return $this->shopwareVersion;
    }
}
