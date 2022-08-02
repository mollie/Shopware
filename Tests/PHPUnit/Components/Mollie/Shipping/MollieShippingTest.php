<?php

namespace MollieShopware\Tests\Components\Mollie\Shipping;

use Mollie\Api\MollieApiClient;
use MollieShopware\Components\Mollie\MollieShipping;
use MollieShopware\Tests\Utils\Fakes\Gateway\FakeMollieGateway;
use PHPUnit\Framework\TestCase;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use stdClass;

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

        # set custom directories to avoid
        # phpstan finding new cache and compile files
        $pluginsDir = __DIR__ . '/../../../../../..';

        $smarty = new \Smarty();
        $smarty->setCompileDir($pluginsDir);
        $smarty->setCacheDir($pluginsDir);

        $this->shipping = new MollieShipping($this->fakeGateway, $smarty);
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
     * This test verifies that we send the carrier if existing, even though
     * our tracking code is not available.
     */
    public function testShipCarrierWithoutTracking()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setTrackingCode('');
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'https://tracking.test'));

        $mollieOrder = $this->getMollieOrder();

        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertSame($mollieOrder, $this->fakeGateway->getShippedOrder());

        $this->assertEquals('MyCarrier', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * This test verifies that we send all required tracking information
     * to Mollie when provided
     */
    public function testShipWithTracking()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'https://tracking.test'));
        $shopwareOrder->setTrackingCode('ABC');

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertSame($mollieOrder, $this->fakeGateway->getShippedOrder());

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('MyCarrier', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('https://tracking.test', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * This test verifies that our tracking also works
     * for partial shipments. Our cart has 1 line item.
     * We try to ship 2 pieces of it, and make sure everything is sent
     * correctly to the Mollie gateway.
     */
    public function testShipPartiallyWithTracking()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setTrackingCode('ABC');

        $detail = $this->getDetail(1, 'olid_123');
        $detail->setQuantity(5);
        $shopwareOrder->setDetails([$detail]);

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrderPartially(
            $shopwareOrder,
            $mollieOrder,
            1,
            2
        );

        $this->assertSame($mollieOrder, $this->fakeGateway->getShippedOrder());
        $this->assertSame('olid_123', $this->fakeGateway->getShippedLineItemId());
        $this->assertSame(2, $this->fakeGateway->getShippedLineItemQuantity());

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('-', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }


    /**
     * @return \string[][]
     */
    public function getAvailableTrackingVariables()
    {
        return [
            'sOrder.trackingcode' => ['{$sOrder.trackingcode}'],
            'offerPosition.trackingcode' => ['{$offerPosition.trackingcode}'],
        ];
    }

    /**
     * This test verifies that the available smarty variables in our tracking
     * URL are correctly replaced with the real tracking code.
     *
     * @dataProvider getAvailableTrackingVariables
     * @param string $variable
     */
    public function testAvailableTrackingVariables($variable)
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'http://track.mollie.local?code=' . $variable));
        $shopwareOrder->setTrackingCode('ABC');

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('http://track.mollie.local?code=ABC', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * This test verifies that we do have a valid url with a valid
     * smarty variable syntax. but the variable is not known.
     * In this case, the url should be empty.
     */
    public function testUnknownTrackingVariables()
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'http://track.mollie.local?code={$unknownVariable}'));
        $shopwareOrder->setTrackingCode('ABC');

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * @return \string[][]
     */
    public function getInvalidTrackingUrls()
    {
        return [
            'invalid-url' => ['no-http-url'],
            'unknown-smarty-variable' => ['https://nolp.dhl.de/de/search?piececode={abc.trackingcode}'],
            'invalid-characters-bracket1' => ['https://nolp.dhl.de/de/search?piececode={'],
            'invalid-characters-bracket2' => ['https://nolp.dhl.de/de/search?piececode=}'],
            'invalid-characters-hashtag' => ['https://nolp.dhl.de/de/search?piececode=#test#'],
            'invalid-characters-less' => ['https://www.dhl.de/de/privatkunden/dhl-sendungsverfolgung.html?Paketnummer=<-Zinfo'],
            'invalid-characters-greater' => ['https://www.dhl.de/de/privatkunden/dhl-sendungsverfolgung.html?Paketnummer=>-Zinfo'],
        ];
    }

    /**
     * This test verifies that invalid URLs
     * are ignored and not passed on to Mollie.
     *
     * @dataProvider getInvalidTrackingUrls
     * @param $invalidTrackingUrl
     */
    public function testSkipInvalidTrackingURL($invalidTrackingUrl)
    {
        $shopwareOrder = new Order();
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', $invalidTrackingUrl));
        $shopwareOrder->setTrackingCode('ABC');

        $mollieOrder = $this->getMollieOrder();


        $this->shipping->shipOrder($shopwareOrder, $mollieOrder);

        $this->assertEquals('ABC', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * If we have a shipping code with length >= 100
     * then we must NOT use tracking because Mollie doesn't allow such
     * a length and would throw an exception.
     */
    public function testSkipTooLongCodes()
    {
        # build a long code that is also too long if split by our separator '.'
        $longCode = str_repeat('1', 100) . '.bbb';
        $this->assertEquals(104, strlen($longCode));

        $shopwareOrder = new Order();
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'https://tracking.test'));
        $shopwareOrder->setTrackingCode($longCode);

        $this->shipping->shipOrder($shopwareOrder, $this->getMollieOrder());

        $this->assertEquals('', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('MyCarrier', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('', $this->fakeGateway->getShippedTrackingUrl());
    }

    /**
     * If we have a shipping code with length >= 100 we also
     * check for a special separator. If we can split by that separator and
     * have a valid length again, then we use the first fragment which is
     * very likely the first tracking code of multiple ones.
     *
     * @testWith    [","]
     *              [";"]
     * @param string $separator
     * @return void
     */
    public function testTooLongCodeUsesFirstFragment($separator)
    {
        $longCode = 'a' . $separator;
        $longCode .= str_repeat('1', 98);
        $this->assertEquals(100, strlen($longCode));

        $shopwareOrder = new Order();
        $shopwareOrder->setDispatch($this->getDispatch(4, 'MyCarrier', 'https://tracking.test'));
        $shopwareOrder->setTrackingCode($longCode);

        $this->shipping->shipOrder($shopwareOrder, $this->getMollieOrder());

        $this->assertEquals('a', $this->fakeGateway->getShippedTrackingNumber());
        $this->assertEquals('MyCarrier', $this->fakeGateway->getShippedCarrier());
        $this->assertEquals('https://tracking.test', $this->fakeGateway->getShippedTrackingUrl());
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
        /** @var Dispatch|\PHPUnit_Framework_MockObject_MockObject $dispatch */
        $dispatch = $this->getMockBuilder(Dispatch::class)->disableOriginalConstructor()->getMock();

        $dispatch->method('getId')->willReturn($id);
        $dispatch->method('getName')->willReturn($carrier);
        $dispatch->method('getStatusLink')->willReturn($trackingUrl);

        return $dispatch;
    }

    /**
     * @param $id
     * @param $mollieLineItemId
     * @return Detail
     */
    private function getDetail($id, $mollieLineItemId)
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject $att */
        $att = $this->getMockBuilder(stdClass::class)->disableOriginalConstructor()
            ->addMethods([
                'getMollieOrderLineId'
            ])
            ->getMock();
        $att->method('getMollieOrderLineId')->willReturn($mollieLineItemId);

        /** @var Detail|\PHPUnit_Framework_MockObject_MockObject $detail */
        $detail = $this->getMockBuilder(Detail::class)->disableOriginalConstructor()->getMock();
        $detail->method('getId')->willReturn($id);
        $detail->method('getAttribute')->willReturn($att);

        return $detail;
    }
}
