<?php

namespace MollieShopware\Tests\Facades\FinishCheckout\Services;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Installer\PaymentMethods\PaymentMethodsInstaller;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class MollieStatusValidatorTest extends TestCase
{
    /**
     * @var MollieStatusValidator
     */
    private $statusValidator;

    /**
     * Creates a new instance of the status
     * validator to run the tests against.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->statusValidator = new MollieStatusValidator();
    }

    /**
     * Tests if the didOrderCheckoutSucceed() method
     * returns false when the order is canceled.
     *
     * @test
     * @testdox Method didOrderCheckoutSucceed() returns false when the order is canceled.
     * @return void
     */
    public function testDidOrderCheckoutSucceedReturnsFalseWhenOrderIsCanceled(): void
    {
        $order = $this->createConfiguredMock(Order::class, [
            'isCanceled' => true,
        ]);

        $expected = false;
        $actual = $this->statusValidator->didOrderCheckoutSucceed($order);

        self::assertSame($expected, $actual);
    }

    /**
     * Tests if the didOrderCheckoutSucceed() method
     * returns false when the order is expired.
     *
     * @test
     * @testdox Method didOrderCheckoutSucceed() returns false when the order is expired.
     * @return void
     */
    public function testDidOrderCheckoutSucceedReturnsFalseWhenOrderIsExpired(): void
    {
        $order = $this->createConfiguredMock(Order::class, [
            'isExpired' => true,
        ]);

        $expected = false;
        $actual = $this->statusValidator->didOrderCheckoutSucceed($order);

        self::assertSame($expected, $actual);
    }

    /**
     * Tests if the didOrderCheckoutSucceed()
     * method returns the expected value.
     *
     * @dataProvider providePaymentTestData();
     * @test
     * @testdox Method didOrderCheckoutSucceed() returns $expected when an order paid with $method has payment status $status.
     * @param string $method
     * @param string $status
     * @param bool $expected
     * @return void
     */
    public function testDidOrderCheckoutSucceedReturnsExpectedValue(string $method, string $status, bool $expected): void
    {
        $order = $this->createConfiguredMock(Order::class, [
            'payments' => $this->createMockPaymentCollection([$this->createMockPayment($method, $status)]),
        ]);

        $actual = $this->statusValidator->didOrderCheckoutSucceed($order);

        self::assertSame($expected, $actual);
    }

    /**
     * Tests if the didPaymentCheckoutSucceed()
     * method returns the expected value.
     *
     * @dataProvider providePaymentTestData();
     * @test
     * @testdox Method didPaymentCheckoutSucceed() returns $expected when a $method payment has status $status.
     * @param string $method
     * @param string $status
     * @param bool $expected
     * @return void
     */
    public function testDidPaymentCheckoutSucceedReturnsExpectedValue(string $method, string $status, bool $expected): void
    {
        $payment = $this->createMockPayment($method, $status);
        $actual = $this->statusValidator->didPaymentCheckoutSucceed($payment);

        self::assertSame($expected, $actual);
    }

    /**
     * Returns a configured payment object mock,
     * containing the payment method and
     * an is<status> method.
     *
     * @param string $method
     * @param string $status
     * @return MockObject|Payment
     */
    private function createMockPayment(string $method, string $status): Payment
    {
        $payment = $this->createConfiguredMock(Payment::class, [
            $this->getIsStatusMethod($status) => true,
        ]);

        $payment->method = $method;

        return $payment;
    }

    /**
     * Returns a payment collection mock, containing
     * the provided payment objects.
     *
     * @param array $payments
     * @return PaymentCollection
     */
    private function createMockPaymentCollection(array $payments): PaymentCollection
    {
        $client = $this->createMock(MollieApiClient::class);
        $collection = new PaymentCollection($client, count($payments), new stdClass());

        foreach ($payments as $payment) {
            $collection->append($payment);
        }

        return $collection;
    }

    /**
     * Converts a status into an is-method
     * name, e.g. paid becomes isPaid.
     *
     * @param string $status
     * @return string
     */
    private function getIsStatusMethod(string $status): string
    {
        switch ($status) {
            case 'open':
                $isMethod = 'isOpen';
                break;
            case 'authorized':
                $isMethod = 'isAuthorized';
                break;
            case 'pending':
                $isMethod = 'isPending';
                break;
            case 'paid':
                $isMethod = 'isPaid';
                break;
            case 'failed':
                $isMethod = 'isFailed';
                break;
            case 'expired':
                $isMethod = 'isExpired';
                break;
            case 'canceled':
                $isMethod = 'isCanceled';
                break;
            default:
                $isMethod = '';
                break;
        }

        if (empty($isMethod)) {
            throw new RuntimeException(
                'The isMethod cannot be empty. It should be one of isOpen, isAuthorized, isPending, etc...'
            );
        }

        return $isMethod;
    }

    /**
     * Provides an array of test data containing
     * different payment methods, statuses
     * and expected outcomes.
     *
     * @return array<array<string, string>>
     */
    public function providePaymentTestData(): array
    {
        $testData = [];
        $successful = ['authorized', 'open', 'pending', 'paid'];
        $unsuccessful = ['canceled', 'expired', 'failed'];

        foreach (PaymentMethodsInstaller::getSupportedPaymentMethods() as $method) {
            $successfulStatuses = $successful;
            $unsuccessfulStatuses = $unsuccessful;

            # we don't know why, but it can happen that a credit card
            # payment has an open status, if so, it's not valid
            if ($method === PaymentMethod::CREDITCARD) {
                $successfulStatuses = array_filter($successfulStatuses, function ($status) {
                    return $status !== 'open';
                });

                $unsuccessfulStatuses[] = 'open';
            }

            # we expect method didPaymentCheckoutSucceed()
            # to return true for successful statuses
            foreach ($successfulStatuses as $status) {
                $testData[sprintf('%s status on %s returns true', $status, $method)] = [
                    $method,
                    $status,
                    true,
                ];
            }

            # we expect method didPaymentCheckoutSucceed()
            # to return false for unsuccessful statuses
            foreach ($unsuccessfulStatuses as $status) {
                $testData[sprintf('%s status on %s returns false', $status, $method)] = [
                    $method,
                    $status,
                    false,
                ];
            }
        }

        return $testData;
    }
}
