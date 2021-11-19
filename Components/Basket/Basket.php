<?php

namespace MollieShopware\Components\Basket;

use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Order\Repository;

class Basket implements BasketInterface
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
     * @var \Shopware\Models\Detail\Repository
     */
    private $repoArticles;

    /**
     * Basket constructor.
     * @param ModelManager $modelManager
     * @param LoggerInterface $logger
     */
    public function __construct(ModelManager $modelManager, LoggerInterface $logger)
    {
        $this->modelManager = $modelManager;
        $this->logger = $logger;

        $this->repoArticles = $modelManager->getRepository(Detail::class);
    }


    /**
     * Get positions from basket
     *
     * @param array $userData
     * @return MollieBasketItem[]
     * @throws \Exception
     */
    public function getMollieBasketLines($userData = [])
    {
        $items = [];

        try {

            /** @var Repository $basketRepo */
            $basketRepo = $this->modelManager->getRepository(\Shopware\Models\Order\Basket::class);

            /** @var Basket[] $basketItems */
            $basketItems = $basketRepo->findBy(['sessionId' => Shopware()->Session()->offsetGet('sessionId')]);

            /** @var \Shopware\Models\Order\Basket $basketItem */
            foreach ($basketItems as $basketItem) {

                $voucherType = '';

                # if we do have an article number
                # then let's try to get its voucher type
                if (!empty($basketItem->getOrderNumber())) {
                    $voucherType = $this->getArticleVoucherType($basketItem);
                }

                $item = new MollieBasketItem(
                    $basketItem->getId(),
                    $basketItem->getArticleId(),
                    $basketItem->getOrderNumber(),
                    $basketItem->getEsdArticle(),
                    $basketItem->getMode(),
                    $basketItem->getArticleName(),
                    $basketItem->getPrice(),
                    $basketItem->getNetPrice(),
                    $basketItem->getQuantity(),
                    $basketItem->getTaxRate(),
                    $voucherType
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
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        return $items;
    }

    /**
     * @param \Shopware\Models\Order\Basket $basketItem
     * @return string
     */
    private function getArticleVoucherType(\Shopware\Models\Order\Basket $basketItem)
    {
        $articles = $this->repoArticles->findBy(['number' => $basketItem->getOrderNumber()]);

        if (count($articles) <= 0) {
            return '';
        }

        /** @var Detail $article */
        $article = $articles[0];

        return (string)$article->getAttribute()->getMollieVoucherType();
    }

}
