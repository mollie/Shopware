<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use Doctrine\ORM\EntityNotFoundException;
use MollieShopware\Components\Config;
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
     * @throws EntityNotFoundException
     * @return Payment
     */
    public function getPaymentMethod()
    {
        $applePayDirect = $this->paymentMethodService->getActiveApplePayDirectMethod();

        if ($applePayDirect instanceof Payment) {
            return $applePayDirect;
        }

        throw new EntityNotFoundException('Apple Pay Direct Payment not found');
    }

    /**
     * @return bool
     */
    public function isApplePayDirectEnabled()
    {
        try {
            $method = $this->getPaymentMethod();

            return $method->getActive();
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * @param $defaultPaymentMethod
     * @return bool
     */
    public function isApplePayPaymentMethod($defaultPaymentMethod)
    {
        $applePayMethods = [
            ShopwarePaymentMethod::APPLEPAY,
            ShopwarePaymentMethod::APPLEPAYDIRECT
        ];

        if (!\in_array($defaultPaymentMethod, $applePayMethods, true)) {
            return false;
        }

        return true;
    }

    /**
     *
     * Gets if the Apple Pay Direct method is blocked due
     * to risk management settings in Shopware.
     *
     * @param \sAdmin $sAdmin
     * @throws EntityNotFoundException
     * @return bool
     */
    public function isRiskManagementBlocked(\sAdmin $sAdmin)
    {
        if (!$this->isApplePayDirectEnabled()) {
            return false;
        }

        $method = $this->getPaymentMethod();

        return $sAdmin->sManageRisks(
            $method->getId(),
            null,
            $sAdmin->sGetUserData()
        );
    }
}
