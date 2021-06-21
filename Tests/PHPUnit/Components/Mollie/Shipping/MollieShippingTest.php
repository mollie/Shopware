<?php

namespace MollieShopware\Tests\Components\Mollie\Shipping;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Tests\Utils\Fakes\Gateway\FakeMollieGateway;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Order;


class MollieShippingTest extends TestCase
{

    /**
     * @var FakeMollieGateway
     */
    private $fakeGateway;

    /**
     * @var MollieShipping
     */
    private $shipping;


    /**
     *
     */
    public function setUp(): void
    {
        $this->fakeGateway = new FakeMollieGateway();
        $this->shipping = new MollieShipping($this->fakeGateway);
    }


    /**
     * This test verifies that we successfully ship our order
     * without any tracking information if no tracking code exists
     */
    public function testShipWithoutTracking()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setTrackingCode(' ');
        $shopwareOrder->setDispatch($this->getDispatch(4, '', ''));

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertSame($mollieOrder, $this->fakeGateway->getShippedOrder());

        $this->assertEquals('', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * This test verifies that we send all required tracking information
     * to Mollie when provided
     */
    public function testShipWithTracking()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setTrackingCode('ABC');
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'https://tracking.test'));

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertSame($mollieOrder, $this->fakeGateway->getShippedOrder());

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('MyCarrier', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('https://tracking.test', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * This test verifies that invalid URLs are ignored and
     * not passed on to Mollie.
     */
    public function testSkipInvalidTrackingURL()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setTrackingCode('ABC');
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'tracking.test'));

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * This test verifies that we successfully ship our order
     * with a tracking information but without an existing dispatch object.
     * In this case, we try to provide the tracking code in Mollie but just use
     * a default symbol for the required carrier.
     */
    public function testShipWithoutDispatchEntity()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setTrackingCode('ABC');

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertSame($mollieOrder, $this->fakeGateway->getShippedOrder());

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('-', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }


    /**
     * @return \Mollie\Api\Resources\Order
     */
    private function getMollieOrder()
    {
        return new \Mollie\Api\Resources\Order(new MollieApiClient());
    }

    /**
     * @param int $id
     * @param string $carrier
     * @param string $trackingUrl
     * @return Dispatch
     */
    private function getDispatch($id, $carrier, $trackingUrl)
    {
        # we have to create a MOCK because theres no way
        # to set the ID for this type of entity!
        /** @var \PHPUnit_Framework_MockObject_MockObject|Dispatch $repoTasks */
        $dispatch = $this->getMockBuilder(Dispatch::class)->disableOriginalConstructor()->getMock();

        $dispatch->method('getId')->willReturn($id);
        $dispatch->method('getName')->willReturn($carrier);
        $dispatch->method('getStatusLink')->willReturn($trackingUrl);

        return $dispatch;
    }

}
