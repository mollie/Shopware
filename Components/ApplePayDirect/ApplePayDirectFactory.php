<?php

namespace MollieShopware\Components\ApplePayDirect;

use Enlight_Components_Snippet_Namespace;
use Mollie\Api\Exceptions\ApiException;
use MollieShopware\Components\ApplePayDirect\Handler\ApplePayDirectHandler;
use MollieShopware\Components\ApplePayDirect\Services\ApplePayFormatter;
use MollieShopware\Components\Config;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Components\Shipping\Shipping;
use MollieShopware\Components\Snippets\SnippetAdapter;

class ApplePayDirectFactory
{
    /**
     * this is the default snippet namespace for our
     * mollie Apple Pay direct translation.
     */
    const SNIPPET_NS = 'frontend/MollieShopware/ApplePayDirect';

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
     * @var Enlight_Components_Snippet_Namespace
     */
    private $snippets;


    /**
     * @param Config $config
     * @param MollieApiFactory $apiFactory
     * @param Shipping $cmpShipping
     * @param \Enlight_Components_Session_Namespace $session
     * @param Enlight_Components_Snippet_Namespace $snippets
     */
    public function __construct(Config $config, MollieApiFactory $apiFactory, Shipping $cmpShipping, \Enlight_Components_Session_Namespace $session, $snippets)
    {
        # attention, modules does not exist in CLI
        $this->admin = Shopware()->Modules()->Admin();
        $this->basket = Shopware()->Modules()->Basket();

        $this->shipping = $cmpShipping;
        $this->session = $session;

        $this->apiFactory = $apiFactory;
        $this->mollieConfig = $config;
        $this->snippets = $snippets;
    }

    /**
     * @throws ApiException
     * @return ApplePayDirectHandler
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

    /**
     * @return ApplePayFormatter
     */
    public function createFormatter()
    {
        $snippetAdapter = new SnippetAdapter(
            $this->snippets,
            self::SNIPPET_NS
        );

        return new ApplePayFormatter($snippetAdapter);
    }
}
