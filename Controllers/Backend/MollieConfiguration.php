<?php

use Doctrine\ORM\EntityManager;
use MollieShopware\Components\Mollie\MollieApiTester;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Shop\Shop;

class Shopware_Controllers_Backend_MollieConfiguration extends Shopware_Controllers_Backend_Application implements CSRFWhitelistAware
{
    protected $model = Transaction::class;


    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;


    /**
     * @return string[]|void
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'openDashboardKeys'
        ];
    }


    /**
     * Opens a deeplink to the Mollie dashboard API keys.
     */
    public function openDashboardKeysAction()
    {
        $this->loadServices();

        $url = 'https://www.mollie.com/dashboard/developers/api-keys';

        header('Location: ' . $url);
        ob_clean();
    }

    /**
     * Iterates through all shops and tests their
     * LIVE and TEST api keys.
     * The result is then returned as text output
     * as well as stored in the logs.
     */
    public function testKeysAction()
    {
        $this->loadServices();

        $apiTester = new MollieApiTester();
        $repoShops = $this->entityManager->getRepository(Shop::class);


        $backendOutput = '';
        $logData = [];

        $shops = $repoShops->findAll();

        foreach ($shops as $shop) {
            $isLiveValid = false;
            $isTestValid = false;

            try {
                $liveClient = $this->apiFactory->createLiveClient($shop->getId());
                $isLiveValid = $apiTester->isConnectionValid($liveClient);
            } catch (Exception $exception) {
                # if we have any errors like "invalid" api key pattern,
                # then its just failed
            }

            try {
                $testClient = $this->apiFactory->createTestClient($shop->getId());
                $isTestValid = $apiTester->isConnectionValid($testClient);
            } catch (Exception $exception) {
                # if we have any errors like "invalid" api key pattern,
                # then its just failed
            }

            $backendOutput .= $this->getBackendShopOutput($shop, $isLiveValid, $isTestValid);

            $logData[] = [
                'shop' => $shop->getName(),
                'liveKey' => $isLiveValid,
                'testKey' => $isTestValid,
            ];
        }

        $this->logger->info(
            'API Key Testing executed in backend.',
            [
                'data' => $logData,
            ]
        );
        echo $backendOutput;
        ob_clean();
    }

    /**
     * @param Shop $shop
     * @param $isLiveValid
     * @param $isTestValid
     * @return string
     */
    private function getBackendShopOutput(Shop $shop, $isLiveValid, $isTestValid)
    {
        $output = $shop->getName() . ' | ';

        if ($isLiveValid) {
            $output .= 'Live: OK, ';
        } else {
            $output .= 'Live: FAILURE, ';
        }

        if ($isTestValid) {
            $output .= 'Test: OK';
        } else {
            $output .= 'Test: FAILURE';
        }

        $output .= '<br />';

        return $output;
    }

    /**
     *
     */
    private function loadServices()
    {
        $this->logger = $this->container->get('mollie_shopware.components.logger');
        $this->entityManager = $this->container->get('models');
        $this->apiFactory = $this->container->get('mollie_shopware.api_factory');
    }
}
