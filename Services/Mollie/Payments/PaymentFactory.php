<?php

namespace MollieShopware\Services\Mollie\Payments;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Services\Mollie\Payments\Requests\ApplePay;
use MollieShopware\Services\Mollie\Payments\Requests\Bancontact;
use MollieShopware\Services\Mollie\Payments\Requests\BankTransfer;
use MollieShopware\Services\Mollie\Payments\Requests\Belfius;
use MollieShopware\Services\Mollie\Payments\Requests\CreditCard;
use MollieShopware\Services\Mollie\Payments\Requests\EPS;
use MollieShopware\Services\Mollie\Payments\Requests\Giftcard;
use MollieShopware\Services\Mollie\Payments\Requests\Giropay;
use MollieShopware\Services\Mollie\Payments\Requests\IDeal;
use MollieShopware\Services\Mollie\Payments\Requests\KBC;
use MollieShopware\Services\Mollie\Payments\Requests\PayLater;
use MollieShopware\Services\Mollie\Payments\Requests\PayPal;
use MollieShopware\Services\Mollie\Payments\Requests\Przelewy24;
use MollieShopware\Services\Mollie\Payments\Requests\SepaDirectDebit;
use MollieShopware\Services\Mollie\Payments\Requests\SliceIt;
use MollieShopware\Services\Mollie\Payments\Requests\Sofort;


class PaymentFactory
{


    /**
     * @param string $paymentMethod
     * @return PaymentInterface
     * @throws \Exception
     */
    public function createByPaymentName($paymentMethod)
    {
        switch ($paymentMethod) {

            case PaymentMethod::APPLE_PAY:
            case PaymentMethod::APPLEPAY_DIRECT:
                return new ApplePay();

            case PaymentMethod::PAYPAL:
                return new PayPal();

            case PaymentMethod::KBC:
                return new KBC();

            case PaymentMethod::EPS:
                return new EPS();

            case PaymentMethod::BANCONTACT:
                return new Bancontact();

            case PaymentMethod::BANKTRANSFER:
                return new BankTransfer();

            case PaymentMethod::BELFIUS:
                return new Belfius();

            case PaymentMethod::CREDITCARD:
                return new CreditCard();

            case PaymentMethod::DIRECTDEBIT:
                return new SepaDirectDebit();

            case PaymentMethod::GIFTCARD:
                return new Giftcard();

            case PaymentMethod::GIROPAY:
                return new Giropay();

            case PaymentMethod::IDEAL:
                return new IDeal();

            case PaymentMethod::KLARNA_PAY_LATER:
                return new PayLater();

            case PaymentMethod::KLARNA_SLICE_IT:
                return new SliceIt();

            case PaymentMethod::P24:
                return new Przelewy24();

            case PaymentMethod::SOFORT:
                return new Sofort();
        }

        throw new \Exception('Payment handler not found for: ' . $paymentMethod);
    }

}
