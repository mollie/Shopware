<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use MollieShopware\Components\Constants\ShopwarePaymentMethod;
use MollieShopware\Components\Services\PaymentMethodService;
use Shopware\Models\Payment\Payment;

class ApplePayPaymentMethod
{

    /**
     * @var PaymentMethodService $paymentMethodService
     */
    private $paymentMethodService;


    /**
     * ApplePayPaymentMethod constructor.
     *
     * @param PaymentMethodService $paymentMethodService
     */
    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
    }

    /**
     * @return Payment
     * @throws \Exception
     */
    public function getPaymentMethod()
    {
        $applePayDirect = $this->paymentMethodService->getPaymentMethod(
            [
                'name' => ShopwarePaymentMethod::APPLEPAYDIRECT,
                'active' => true,
            ]
        );

        if ($applePayDirect instanceof Payment) {
            return $applePayDirect;
        }

        throw new \Exception('Apple Pay Direct Payment not found');
    }

    /**
     * @return bool
     */
    public function isApplePayDirectEnabled()
    {
        try {
            $this->getPaymentMethod();

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

}
