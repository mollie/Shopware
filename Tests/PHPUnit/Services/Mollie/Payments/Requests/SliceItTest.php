<?php

namespace MollieShopware\Tests\Services\Mollie\Payments\Requests;

use MollieShopware\Services\Mollie\Payments\Exceptions\ApiNotSupportedException;
use MollieShopware\Services\Mollie\Payments\Models\Payment;
use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;
use MollieShopware\Services\Mollie\Payments\Requests\SliceIt;
use MollieShopware\Tests\Utils\Traits\PaymentTestTrait;
use PHPUnit\Framework\TestCase;


class SliceItTest extends TestCase
{
    use PaymentTestTrait;


    /**
     * @var SliceIt
     */
    private $payment;

    /**
     * @var PaymentAddress
     */
    private $address;

    /**
     * @var PaymentLineItem
     */
    private $lineItem;

    /**
     *
     */
    public function setUp(): void
    {
        $this->payment = new SliceIt();

        $this->address = $this->getAddressFixture();
        $this->lineItem = $this->getLineItemFixture();

        $this->payment->setPayment(
            new Payment(
                'UUID-123',
                'Payment UUID-123',
                '20004',
                $this->address,
                $this->address,
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
     * Please note, Klarna does not work with the payments API
     * so this is just an empty array
     *
     * @covers  \MollieShopware\Services\Mollie\Payments\Requests\SliceIt
     */
    public function testPaymentsAPI()
    {
        $this->expectException(ApiNotSupportedException::class);

        $this->payment->buildBodyPaymentsAPI();
    }

    /**
     * This test verifies that the Orders-API request
     * for our payment is correct.
     *
     * @covers \MollieShopware\Services\Mollie\Payments\Requests\SliceIt
     */
    public function testOrdersAPI()
    {
        $expected = [
            'method' => 'klarnasliceit',
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
            'billingAddress' => $this->getExpectedAddressStructure($this->address),
            'shippingAddress' => $this->getExpectedAddressStructure($this->address),
            'lines' => [
                $this->getExpectedLineItemStructure($this->lineItem),
            ],
            'metadata' => [],
        ];

        $requestBody = $this->payment->buildBodyOrdersAPI();

        $this->assertSame($expected, $requestBody);
    }

}
