<?php

use MollieShopware\Facades\Shipping\ShipOrderFacade;
use Psr\Log\LoggerInterface;


class Shopware_Controllers_Api_Mollie extends Shopware_Controllers_Api_Rest
{

    /**
     * @var ShipOrderFacade
     */
    private $shipOrderFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     *
     */
    public function init()
    {
        $this->shipOrderFacade = new ShipOrderFacade(
            Shopware()->Container()->get('mollie_shopware.order_service'),
            Shopware()->Container()->get('mollie_shopware.gateways.mollie.factory'),
            Shopware()->Container()->get('template')
        );

        $this->logger = Shopware()->Container()->get('mollie_shopware.components.logger');
    }

    /**
     * This is required to have our REST setup on the one hand, which allows
     * us to use the API with AUTH mode of Shopware, but also have
     * the option to use our own actions instead of REST on the other hand.
     * That means we have our "/mollie" slug, and can add whatever resource action we need.
     *
     * @param string $name
     * @param null $value
     * @throws Enlight_Controller_Exception
     * @throws Enlight_Exception
     */
    public function __call($name, $value = null)
    {
        $slug = $this->request->getPathInfo();
        $slug = str_replace('/api/mollie', '', $slug);

        switch ($slug) {
            case '/ship/order':
                $this->shipOrderAction();
                break;

            case '/ship/item':
                $this->shipItemAction();
                break;

            default:
                return parent::__call($name, $value);
        }
    }

    /**
     *
     */
    public function shipOrderAction()
    {
        try {

            /** @var null|string $orderNumber */
            $orderNumber = $this->Request()->getParam('number', null);

            if ($orderNumber === null) {
                throw new InvalidArgumentException('Missing Argument for Order Number!');
            }

            $this->logger->info('Starting full shipment on API for Order: ' . $orderNumber);

            $this->shipOrderFacade->shipOrder($orderNumber, null, null);

            $this->logger->info('Shipping Success on API for Order: ' . $orderNumber);

            $this->View()->assign([
                'success' => true
            ]);

        } catch (\Exception $e) {

            $this->logger->error(
                'Error when processing full shipment for Order ' . $orderNumber . ' on API',
                [
                    'error' => $e->getMessage(),
                ]
            );

            $this->View()->assign([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     *
     */
    public function shipItemAction()
    {
        try {

            /** @var null|string $orderNumber */
            $orderNumber = $this->Request()->getParam('order', null);

            /** @var null|string $articleNumber */
            $articleNumber = $this->Request()->getParam('article', null);

            /** @var null|int $shipQuantity */
            $shipQuantity = $this->Request()->getParam('quantity', null);


            if ($orderNumber === null) {
                throw new InvalidArgumentException('Missing Order Number!');
            }

            if ($articleNumber === null) {
                throw new InvalidArgumentException('Missing Article Number!');
            }

            $this->logger->info('Starting partial shipment on API for Order: ' . $orderNumber . ', Article: ' . $articleNumber);

            $this->shipOrderFacade->shipOrder($orderNumber, $articleNumber, $shipQuantity);

            $this->logger->info('Partial Shipping Success on API for Order: ' . $orderNumber . ', Article: ' . $articleNumber);

            $this->View()->assign([
                'success' => true
            ]);

        } catch (\Exception $e) {

            $this->logger->error(
                'Error when processing partial shipment for Order ' . $orderNumber . ' on API',
                [
                    'error' => $e->getMessage(),
                ]
            );

            $this->View()->assign([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
