<?php

namespace MollieShopware\Components\Services;

use Shopware\Components\Model\ModelManager;
use Shopware\Models\Shop\Repository as ShopRepository;
use Shopware\Models\Shop\Shop;

class ShopService
{
    /** @var ModelManager */
    private $modelManager;

    /**
     * Creates a new instance of the shop helper.
     *
     * @param ModelManager $modelManager
     */
    public function __construct(
        ModelManager $modelManager
    )
    {
        $this->modelManager = $modelManager;
    }

    /**
     * Returns a shop by it's id.
     *
     * @param int $id
     * @return Shop|object|null
     */
    public function shopById($id)
    {
        /** @var ShopRepository $shopRepo */
        $shopRepo = $this->modelManager->getRepository(Shop::class);

        /** @var Shop $shop */
        return $shopRepo->findOneBy([
            'id' => $id
        ]);
    }
}