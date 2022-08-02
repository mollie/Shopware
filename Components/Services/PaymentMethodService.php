<?php

namespace MollieShopware\Components\Services;

use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Payment\Repository as PaymentRepository;

class PaymentMethodService
{

    /**
     * @var ModelManager
     */
    private $modelManager;


    /**
     * @param ModelManager $modelManager
     */
    public function __construct(ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }


    /**
     * Gets the apple pay direct payment method
     * if existing and active
     *
     * @return null|object|Payment
     */
    public function getActiveApplePayDirectMethod()
    {
        /** @var PaymentRepository $paymentMethodRepository */
        $paymentRepository = $this->modelManager->getRepository(Payment::class);

        return $paymentRepository->findOneBy([
            'name' => ShopwarePaymentMethod::APPLEPAYDIRECT,
            'active' => true,
        ]);
    }
}
