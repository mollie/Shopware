<?php

namespace MollieShopware\Components\Services;

use Doctrine\Common\Collections\Collection;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Shop\Repository as ShopRepository;
use Shopware\Models\Shop\Shop;

class ShopService
{
    /** @var ShopRepository $shopRepo */
    private $shopRepo;

    /**
     * Creates a new instance of the shop helper.
     *
     * @param ModelManager $modelManager
     */
    public function __construct(
        ModelManager $modelManager
    ) {
        $this->shopRepo = $modelManager->getRepository(Shop::class);
    }

    /**
     * Returns a shop by it's id.
     *
     * @param int $id
     * @return Shop|object|null
     */
    public function shopById($id)
    {
        /** @var Shop $shop */
        return $this->shopRepo->findOneBy([
            'id' => $id
        ]);
    }

    /**
     * Returns a collection of all shops.
     *
     * @return Shop[]
     */
    public function getAllShops()
    {
        return $this->shopRepo->findAll();
    }
}
