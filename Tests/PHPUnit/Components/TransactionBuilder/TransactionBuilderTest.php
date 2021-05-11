<?php

namespace MollieShopware\Tests\Components\TransactionBuilder;


use MollieShopware\Components\TransactionBuilder\TransactionBuilder;
use MollieShopware\Tests\Utils\Fakes\Basket\FakeBasket;
use MollieShopware\Tests\Utils\Fakes\Session\FakeSession;
use MollieShopware\Tests\Utils\Fakes\Shipping\FakeShipping;
use MollieShopware\Tests\Utils\Fakes\Transaction\FakeTransactionRepository;
use MollieShopware\Tests\Utils\Fixtures\BasketLineItemFixture;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Customer\Customer;


class TransactionBuilderTest extends TestCase
{

    /**
     * @var BasketLineItemFixture
     */
    private $itemsFixture;

    /**
     * @var TransactionBuilder
     */
    private $sampleBuilder;

    /**
     *
     */
    public function setUp(): void
    {
        $this->itemsFixture = new BasketLineItemFixture();

        $this->sampleBuilder = new TransactionBuilder(
            new FakeSession('session-123'),
            new FakeTransactionRepository(),
            new FakeBasket([
                $this->itemsFixture->buildProductItemGross(19.99, 1, 19),
            ]),
            new FakeShipping(
                $this->itemsFixture->buildProductItemGross(7.99, 1, 19)
            ),
            false
        );
    }


    /**
     * @return array[]
     */
    public function getCheckoutNetShopData()
    {
        return [
            'Case 1' => [22.15, [6.0, 1, 6.00 + 1.14], [5.9, 1, 5.9 + 1.12], [7.99, 1, 7.99]],
            'Case 2' => [149.60, [6.0, 10, 60.0 + 11.40], [5.9, 10, 59 + 11.21], [7.99, 1, 7.99]],
            'Case 4' => [620.05, [6.9, 66, 541.86], [5.9, 10, 70.2], [7.99, 1, 7.99]],
        ];
    }

    /**
     * This test verifies the correct amounts for a B2B net based shop.
     * In this type of shop, the article prices are maintained in net prices.
     * This means the prices need to be converted into gross prices for Mollie.
     * The shipping line item however is maintained in gross in Shopware.
     * To avoid wrong calculations, we just reuse that gross price instead of
     * calculating it from the (1 cent off) net price of Shopware.
     *
     * @dataProvider getCheckoutNetShopData
     *
     * @param $shopwareTotalAmount
     * @param $product1
     * @param $product2
     * @param $product3
     */
    public function testTransactionNetShop($shopwareTotalAmount, $product1, $product2, $product3)
    {
        $builder = new TransactionBuilder(
            new FakeSession('session-123'),
            new FakeTransactionRepository(),
            new FakeBasket([
                $this->itemsFixture->buildProductItemNet($product1[0], $product1[1], 19),
                $this->itemsFixture->buildProductItemNet($product2[0], $product2[1], 19)
            ]),
            new FakeShipping(
                $this->itemsFixture->buildProductItemGross($product3[0], $product3[1], 19)
            ),
            false
        );

        # ---------------------------------------------------------------------------

        $transaction = $builder->buildTransaction(
            'signature-123',
            'EUR',
            $shopwareTotalAmount,
            2,
            [],
            'de-DE',
            null,
            false,
            true
        );

        $itemSum = $transaction->getItems()[0]->getTotalAmount() + $transaction->getItems()[1]->getTotalAmount() + $transaction->getItems()[2]->getTotalAmount();

        # ---------------------------------------------------------------------------

        $this->assertEquals(false, $transaction->getTaxFree());
        $this->assertEquals(true, $transaction->getNet());

        $this->assertEquals($shopwareTotalAmount, $transaction->getTotalAmount(), 'Total sum is not the defined sum of Shopware!');
        $this->assertEquals($shopwareTotalAmount, $itemSum, 'Sum of line items does not match total sum');

        $this->assertEquals($product1[2], $transaction->getItems()[0]->getTotalAmount(), 'Total Amount of Item 1 not correct!');
        $this->assertEquals($product2[2], $transaction->getItems()[1]->getTotalAmount(), 'Total Amount of Item 2 not correct!');
        $this->assertEquals($product3[2], $transaction->getItems()[2]->getTotalAmount(), 'Total Amount of Item 3 not correct!');
    }


    /**
     * @return array[]
     */
    public function getCheckoutGrossShopData()
    {
        return [
            'Case 1' => [1387.23, [19.99, 66, 1319.34], [5.99, 10, 59.90], [7.99, 1, 7.99]],
        ];
    }

    /**
     *
     * @dataProvider  getCheckoutGrossShopData
     *
     * @param $shopwareTotalAmount
     * @param $product1
     * @param $product2
     * @param $product3
     */
    public function testTransactionGrossShop($shopwareTotalAmount, $product1, $product2, $product3)
    {
        $builder = new TransactionBuilder(
            new FakeSession('session-123'),
            new FakeTransactionRepository(),
            new FakeBasket([
                $this->itemsFixture->buildProductItemGross($product1[0], $product1[1], 19),
                $this->itemsFixture->buildProductItemGross($product2[0], $product2[1], 19)
            ]),
            new FakeShipping(
                $this->itemsFixture->buildProductItemGross($product3[0], $product3[1], 19)
            ),
            false
        );

        # ---------------------------------------------------------------------------

        $transaction = $builder->buildTransaction(
            'signature-123',
            'EUR',
            $shopwareTotalAmount,
            2,
            [],
            'de-DE',
            null,
            false,
            false
        );

        $itemSum = $transaction->getItems()[0]->getTotalAmount() + $transaction->getItems()[1]->getTotalAmount() + $transaction->getItems()[2]->getTotalAmount();

        # ---------------------------------------------------------------------------

        $this->assertEquals(false, $transaction->getTaxFree());
        $this->assertEquals(false, $transaction->getNet());

        $this->assertEquals($shopwareTotalAmount, $transaction->getTotalAmount(), 'Total sum is not the defined sum of Shopware!');
        $this->assertEquals($shopwareTotalAmount, $itemSum, 'Sum of line items does not match total sum');

        $this->assertEquals($product1[2], $transaction->getItems()[0]->getTotalAmount(), 'Total Amount of Item 1 not correct!');
        $this->assertEquals($product2[2], $transaction->getItems()[1]->getTotalAmount(), 'Total Amount of Item 2 not correct!');
        $this->assertEquals($product3[2], $transaction->getItems()[2]->getTotalAmount(), 'Total Amount of Item 3 not correct!');
    }


    /**
     * This test verifies that all our secondary data is correctly set.
     * That includes things like locale, currency and more.
     */
    public function testOtherTransactionData()
    {
        $transaction = $this->sampleBuilder->buildTransaction(
            'signature-123',
            'EUR',
            19.99 + 7.99,
            2,
            [],
            'de-DE',
            null,
            false,
            false
        );

        # ---------------------------------------------------------------------------

        $this->assertEquals(6, $transaction->getId());
        $this->assertEquals('mollie_6', $transaction->getTransactionId());

        $this->assertEquals(2, $transaction->getShopId());
        $this->assertEquals('session-123', $transaction->getSessionId());
        $this->assertEquals('signature-123', $transaction->getBasketSignature());

        $this->assertEquals('de-DE', $transaction->getLocale());
        $this->assertEquals('EUR', $transaction->getCurrency());

        $this->assertEquals(null, $transaction->getCustomerId());
        $this->assertEquals(null, $transaction->getCustomer());
        $this->assertEquals(null, $transaction->getOrderNumber());
        $this->assertEquals(null, $transaction->getOrderId());
    }

    /**
     * This test verifies that our customer is correctly set
     * if an object has been provided.
     */
    public function testCustomer()
    {
        $customer = new Customer();

        $transaction = $this->sampleBuilder->buildTransaction(
            'signature-123',
            'EUR',
            19.99 + 7.99,
            2,
            [],
            'de-DE',
            $customer,
            false,
            false
        );

        # ---------------------------------------------------------------------------

        $this->assertEquals(null, $transaction->getCustomerId());
        $this->assertSame($customer, $transaction->getCustomer());
    }

}
