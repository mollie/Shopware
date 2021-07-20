<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Requests;

use MollieShopware\Services\Mollie\Payments\Models\Payment;
use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;
use MollieShopware\Services\Mollie\Payments\Requests\SepaDirectDebit;
use MollieShopware\Tests\Utils\Traits\PaymentTestTrait;
use PHPUnit\Framework\TestCase;


class SepaDirectDebitTest extends TestCase
{
    use PaymentTestTrait;


    /**
     * @var SepaDirectDebit
     */
    private $payment;

    /**
     * @var PaymentAddress
     */
    private $addressInvoice;

    /**
     * @var PaymentAddress
     */
    private $addressShipping;


    /**
     * @var PaymentLineItem
     */
    private $lineItem;

    /**
     *
     */
    public function setUp(): void
    {
        $this->payment = new SepaDirectDebit();

        $this->addressInvoice = $this->getAddressFixture1();
        $this->addressShipping = $this->getAddressFixture2();
        $this->lineItem = $this->getLineItemFixture();

        $this->payment->setPayment(
            new Payment(
                'UUID-123',
                'Payment UUID-123',
                '20004',
                $this->addressInvoice,
                $this->addressShipping,
                49.98,
                [$this->lineItem],
                'USD',
                'de_DE',
                'https://local/redirect',
                'https://local/notify'
            )
        );
    }


    /**
     * This test verifies that the Payments-API request
     * for our payment is correct.
     */
    public function testPaymentsAPI()
    {
        $expected = [
            'method' => 'directdebit',
            'amount' => [
                'currency' => 'USD',
                'value' => '49.98',
            ],
            'description' => 'Payment UUID-123',
            'redirectUrl' => 'https://local/redirect',
            'webhookUrl' => 'https://local/notify',
            'locale' => 'de_DE',
        ];

        $requestBody = $this->payment->buildBodyPaymentsAPI();

        $this->assertEquals($expected, $requestBody);
    }

    /**
     * This test verifies that the Orders-API request
     * for our payment is correct.
     */
    public function testOrdersAPI()
    {
        $expected = [
            'method' => 'directdebit',
            'amount' => [
                'currency' => 'USD',
                'value' => '49.98',
            ],
            'redirectUrl' => 'https://local/redirect',
            'webhookUrl' => 'https://local/notify',
            'locale' => 'de_DE',
            'orderNumber' => '20004',
            'payment' => [
                'webhookUrl' => 'https://local/notify',
            ],
            'billingAddress' => $this->getExpectedAddressStructure($this->addressInvoice),
            'shippingAddress' => $this->getExpectedAddressStructure($this->addressShipping),
            'lines' => [
                $this->getExpectedLineItemStructure($this->lineItem),
            ],
            'metadata' => [],
        ];

        $requestBody = $this->payment->buildBodyOrdersAPI();

        $this->assertSame($expected, $requestBody);
    }

    /**
     * This test verifies that we can set a custom expiration date
     * for our Orders API request.
     */
    public function testExpirationDate()
    {
        $dueInDays = 5;
        $expectedDueDate = date('Y-m-d', strtotime(' + ' . $dueInDays . ' day'));

        $this->payment->setExpirationDays($dueInDays);
        $request = $this->payment->buildBodyOrdersAPI();

        $this->assertEquals($expectedDueDate, $request['expiresAt']);
    }

}
