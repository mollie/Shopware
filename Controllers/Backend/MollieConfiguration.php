<?php

use Doctrine\ORM\EntityManager;
use MollieShopware\Components\MollieApi\MollieApiTester;
use MollieShopware\Components\MollieApiFactory;
use MollieShopware\Models\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Models\Shop\Shop;


class Shopware_Controllers_Backend_MollieConfiguration extends Shopware_Controllers_Backend_Application
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

        die($backendOutput);
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
