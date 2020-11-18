<?php

namespace MollieShopware\Components\Basket;

use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Repository;

class Basket
{

    /**
     * @var ModelManager $modelManager
     */
    private $modelManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    
    /**
     * Basket constructor.
     * @param ModelManager $modelManager
     * @param LoggerInterface $logger
     */
    public function __construct(ModelManager $modelManager, LoggerInterface $logger)
    {
        $this->modelManager = $modelManager;
        $this->logger = $logger;
    }


    /**
     * Get positions from basket
     *
     * @param array $userData
     * @return BasketItem[]
     * @throws \Exception
     */
    public function getBasketLines($userData = array())
    {
        $items = [];

        try {

            /** @var Repository $basketRepo */
            $basketRepo = $this->modelManager->getRepository(\Shopware\Models\Order\Basket::class);

            /** @var Basket[] $basketItems */
            $basketItems = $basketRepo->findBy(['sessionId' => Shopware()->Session()->offsetGet('sessionId')]);
       
            /** @var \Shopware\Models\Order\Basket $basketItem */
            foreach ($basketItems as $basketItem) {

                $item = new BasketItem(
                    $basketItem->getId(),
                    $basketItem->getArticleId(),
                    $basketItem->getOrderNumber(),
                    $basketItem->getEsdArticle(),
                    $basketItem->getMode(),
                    $basketItem->getArticleName(),
                    $basketItem->getPrice(),
                    $basketItem->getNetPrice(),
                    $basketItem->getQuantity(),
                    $basketItem->getTaxRate()
                );
               
                # update our basket item ID if we have that attribute
                # i dont know why - isn't it in the ID already?, but let's keep with it for now
                if ($basketItem !== null && $basketItem->getAttribute() !== null && method_exists($basketItem->getAttribute(), 'setBasketItemId')) {

                    $basketItem->getAttribute()->setBasketItemId($basketItem->getId());

                    $this->modelManager->persist($basketItem);
                    $this->modelManager->flush($basketItem);
                }

                $items[] = $item;
            }

        } catch (\Exception $ex) {

            $this->logger->error(
                'Error when loading basket lines',
                array(
                    'error' => $ex->getMessage(),
                )
            );
        }

        return $items;
    }

}