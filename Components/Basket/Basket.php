<?php

namespace MollieShopware\Components\Basket;

use Enlight_View;
use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
use MollieShopware\Models\Voucher\VoucherType;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;
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
     * @throws \Exception
     * @return MollieBasketItem[]
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
     * Returns the voucher type for an article.
     *
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
        $attribute = $article->getAttribute();
        if ($attribute === null) {
            return '';
        }
        if (!method_exists($attribute, 'getMollieVoucherType')) {
            $this->logger->warning('Method getMollieVoucherType is not existing in Article Attributes. Please clear your cache!');
            return VoucherType::NONE;
        }

        return (string)$article->getAttribute()->getMollieVoucherType();
    }

    /**
     * Returns a basket array for the given view.
     *
     * @param Enlight_View $view
     * @return array
     */
    public function getBasketForView(Enlight_View $view)
    {
        return isset($view->sBasket) && is_array($view->sBasket) ? $view->sBasket : [];
    }

    /**
     * Returns a BasketAmount DTO for the given view.
     *
     * @param Enlight_View $view
     * @return BasketAmount
     */
    public function getBasketAmountForView(Enlight_View $view)
    {
        $basket = $this->getBasketForView($view);

        return new BasketAmount((float) $basket['AmountNumeric'], $basket['sCurrencyName']);
    }
}
