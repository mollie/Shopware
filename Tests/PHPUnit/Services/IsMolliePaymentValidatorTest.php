<?php

namespace MollieShopware\Tests\PHPUnit\Services;

use MollieShopware\MollieShopware;
use MollieShopware\Services\IsMolliePaymentValidator;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Plugin\Plugin;

class IsMolliePaymentValidatorTest extends TestCase
{
    private IsMolliePaymentValidator $isMolliePaymentValidator;

    protected function setUp(): void
    {
        $this->isMolliePaymentValidator = new IsMolliePaymentValidator('MollieShopware');
    }

    /**
     * @dataProvider validateDataProvider
     */
    public function testValidate(Payment $payment, bool $expected)
    {
        $this->assertSame($expected, $this->isMolliePaymentValidator->validate($payment));
    }

    public static function validateDataProvider()
    {
        $molliePlugin = new Plugin();
        $molliePlugin->setName('MollieShopware');

        $prepayment = new Payment();
        $prepayment->setName('prepayment');

        $invoice = new Payment();
        $invoice->setName('invoice');

        $creditCard = new Payment();
        $creditCard->setName(MollieShopware::PAYMENT_PREFIX . 'credit_card');
        $creditCard->setPlugin($molliePlugin);

        $klarnaPayNow = new Payment();
        $klarnaPayNow->setName(MollieShopware::PAYMENT_PREFIX . 'klarna_pay_now');
        $klarnaPayNow->setPlugin($molliePlugin);

        return [
            [$prepayment, false],
            [$invoice, false],
            [$creditCard, true],
            [$klarnaPayNow, true],
        ];
    }
}
