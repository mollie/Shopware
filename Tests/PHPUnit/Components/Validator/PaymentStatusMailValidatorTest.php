<?php

namespace MollieShopware\Tests\Components\Validator;

use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Validator\PaymentStatusMailValidator;
use MollieShopware\MollieShopware;
use MollieShopware\Tests\Utils\Fakes\Config\FakeConfig;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Order\Order;

use Shopware\Models\Order\Status;
use Shopware\Models\Payment\Payment;

class PaymentStatusMailValidatorTest extends TestCase
{

    /**
     * @var Order
     */
    private $order;


    /**
     *
     */
    public function setUp(): void
    {
        $status = new Status();
        $status->setName('authorized');

        $payment = new Payment();
        $payment->setName(MollieShopware::PAYMENT_PREFIX . PaymentMethod::PAYPAL);

        $this->order = new Order();
        $this->order->setPayment($payment);
        $this->order->setPaymentStatus($status);
    }

    /**
     * This test verifies that we always send a mail if its configured.
     * No matter what status is set, it should be sent.
     */
    public function testSendIfConfigured()
    {
        $config = new FakeConfig(true);
        $validator = new PaymentStatusMailValidator($config);

        $this->order->getPaymentStatus()->setName('something');

        $sendMail = $validator->shouldSendPaymentStatusMail($this->order, 'something-else');

        $this->assertEquals(true, $sendMail);
    }

    /**
     * This test verifies that we do not send mails if its not configured.
     * No matter what status is set, it should be sent.
     */
    public function testDontSendIfConfigured()
    {
        $config = new FakeConfig(false);
        $validator = new PaymentStatusMailValidator($config);

        $this->order->getPaymentStatus()->setName('something');

        $sendMail = $validator->shouldSendPaymentStatusMail($this->order, 'something-else');

        $this->assertEquals(false, $sendMail);
    }

    /**
     * This test verifies that we do not send klarna mails that are "paid".
     * The mail would be configured, but the customer did not really pay Klarna at that time.
     * Only Mollie has that status, so this would confuse the customer.
     */
    public function testDontSendPaidKlarna()
    {
        $config = new FakeConfig(true);
        $validator = new PaymentStatusMailValidator($config);

        $this->order->getPayment()->setName(MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_LATER);
        $this->order->getPaymentStatus()->setName('authorized');

        $sendMail = $validator->shouldSendPaymentStatusMail($this->order, PaymentStatus::MOLLIE_PAYMENT_PAID);

        $this->assertEquals(false, $sendMail);
    }

    /**
     * This test verifies that we do not send klarna mails that are "completed".
     * The mail would be configured, but the customer did not really pay Klarna at that time.
     * Only Mollie has that status, so this would confuse the customer.
     */
    public function testDontSendCompletedKlarna()
    {
        $config = new FakeConfig(true);
        $validator = new PaymentStatusMailValidator($config);

        $this->order->getPayment()->setName(MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_LATER);
        $this->order->getPaymentStatus()->setName('authorized');

        $sendMail = $validator->shouldSendPaymentStatusMail($this->order, PaymentStatus::MOLLIE_PAYMENT_COMPLETED);

        $this->assertEquals(false, $sendMail);
    }

    /**
     * This test verifies that we send klarna mails that are something else than paid or completed.
     * This means, that Klarna mails should still work for all other cases.
     */
    public function testSendAnyOtherKlarna()
    {
        $config = new FakeConfig(true);
        $validator = new PaymentStatusMailValidator($config);

        $this->order->getPayment()->setName(MollieShopware::PAYMENT_PREFIX . PaymentMethod::KLARNA_PAY_LATER);
        $this->order->getPaymentStatus()->setName('paid');

        $sendMail = $validator->shouldSendPaymentStatusMail($this->order, PaymentStatus::MOLLIE_PAYMENT_REFUNDED);

        $this->assertEquals(true, $sendMail);
    }
}
