<?php

namespace MollieShopware\Components\ApplePayDirect;

use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Components\ApplePayDirect\Handler\ApplePayDirectHandler;
use MollieShopware\Components\Config;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Shipping\Shipping;

/**
 * @copyright 2020 dasistweb GmbH (https://www.dasistweb.de)
 */
class ApplePayDirectFactory
{

    /**
     * @var Config $mollieConfig
     */
    private $mollieConfig;

    /**
     * @var MollieApiFactory $apiFactory
     */
    private $apiFactory;

    /**
     * @var \sAdmin
     */
    private $admin;

    /**
     * @var \sBasket
     */
    private $basket;

    /**
     * @var Shipping $shipping
     */
    private $shipping;

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;


    /**
     * ApplePayDirectFactory constructor.
     *
     * @param Config $config
     * @param MollieApiFactory $apiFactory
     * @param $modules
     * @param Shipping $cmpShipping
     * @param \Enlight_Components_Session_Namespace $session
     */
    public function __construct(Config $config, MollieApiFactory $apiFactory, Shipping $cmpShipping, \Enlight_Components_Session_Namespace $session)
    {
        # attention, modules does not exist in CLI
        $this->admin = Shopware()->Modules()->Admin();
        $this->basket = Shopware()->Modules()->Basket();

        $this->shipping = $cmpShipping;
        $this->session = $session;

        $this->apiFactory = $apiFactory;
        $this->mollieConfig = $config;
    }

    /**
     * @return ApplePayDirectHandler
     * @throws ApiException
     */
    public function createHandler()
    {
        return new ApplePayDirectHandler(
            $this->apiFactory->createLiveClient(),
            $this->admin,
            $this->basket,
            $this->shipping,
            $this->session
        );
    }

}