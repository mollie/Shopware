<?php

namespace MollieShopware\Tests\PHPUnit\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Enlight_Event_EventArgs;
use MollieShopware\Components\Services\StockService;
use MollieShopware\Subscriber\StockSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class StockSubscriberTest extends TestCase
{
    public function testPaymentStateForStockIncrease()
    {
        $expected = [
            Status::PAYMENT_STATE_COMPLETELY_PAID,
            Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED,
        ];

        $this->assertSame($expected, StockSubscriber::PAYMENT_STATE_FOR_STOCK_INCREASE);
    }

    public function testGetSubscribedEvents()
    {
        $expected = [
            'Shopware_Modules_Order_SaveOrder_OrderCreated' => 'resetStocks',
            'sOrder::setPaymentStatus::after' => 'increaseStocks',
            'Shopware\Models\Order\Order::postUpdate' => 'entityEvent',
        ];

        $this->assertSame($expected, StockSubscriber::getSubscribedEvents());
    }

    public function testResetStocks()
    {
        $orderId = 123;

        $stockServiceMock = $this->createMock(StockService::class);
        $stockServiceMock->expects(self::once())->method('updateOrderStocks')->with($orderId, true);

        $event = new Enlight_Event_EventArgs([
            'orderId' => $orderId,
        ]);

        $stockSubscriber = new StockSubscriber($stockServiceMock);
        $stockSubscriber->resetStocks($event);
    }

    /**
     * @dataProvider increaseStocksDataProvider
     * @param mixed $paymentStatusId
     * @param mixed $methodIsExpectedToBeCalled
     */
    public function testIncreaseStocks($paymentStatusId, $methodIsExpectedToBeCalled)
    {
        $orderId = 123;

        $expectedExecutionCount = $methodIsExpectedToBeCalled ? self::once() : self::never();

        $stockServiceMock = $this->createMock(StockService::class);
        $stockServiceMock->expects($expectedExecutionCount)->method('updateOrderStocks')->with($orderId, false);

        $event = new Enlight_Event_EventArgs([
            'orderId' => $orderId,
            'paymentStatusId' => $paymentStatusId,
        ]);

        $stockSubscriber = new StockSubscriber($stockServiceMock);
        $stockSubscriber->increaseStocks($event);
    }

    public static function increaseStocksDataProvider()
    {
        return [
            [Status::PAYMENT_STATE_PARTIALLY_INVOICED, false],
            [Status::PAYMENT_STATE_COMPLETELY_INVOICED, false],
            [Status::PAYMENT_STATE_PARTIALLY_PAID, false],
            [Status::PAYMENT_STATE_COMPLETELY_PAID, true],
            [Status::PAYMENT_STATE_1ST_REMINDER, false],
            [Status::PAYMENT_STATE_2ND_REMINDER, false],
            [Status::PAYMENT_STATE_3RD_REMINDER, false],
            [Status::PAYMENT_STATE_ENCASHMENT, false],
            [Status::PAYMENT_STATE_OPEN, false],
            [Status::PAYMENT_STATE_RESERVED, false],
            [Status::PAYMENT_STATE_DELAYED, false],
            [Status::PAYMENT_STATE_RE_CREDITING, false],
            [Status::PAYMENT_STATE_REVIEW_NECESSARY, false],
            [Status::PAYMENT_STATE_NO_CREDIT_APPROVED, false],
            [Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_PRELIMINARILY_ACCEPTED, false],
            [Status::PAYMENT_STATE_THE_CREDIT_HAS_BEEN_ACCEPTED, false],
            [Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED, true],
            [Status::PAYMENT_STATE_A_TIME_EXTENSION_HAS_BEEN_REGISTERED, false],
            [Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED, false],
        ];
    }

    public function testEntityEventPaymentStatusHasntChanged()
    {
        $stockServiceMock = $this->createMock(StockService::class);
        $stockServiceMock->expects(self::never())->method('updateOrderStocks');

        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $unitOfWorkMock->method('getEntityChangeSet')->willReturn([
            'foo' => 'bar',
        ]);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->method('getUnitOfWork')->willReturn($unitOfWorkMock);

        $event = new Enlight_Event_EventArgs([
            'entityManager' => $entityManagerMock,
            'entity' => new Order(),
        ]);

        $stockSubscriber = new StockSubscriber($stockServiceMock);
        $stockSubscriber->entityEvent($event);
    }

    /**
     * @dataProvider entityEventDataProvider
     * @param mixed $methodIsExpectedToBeCalled
     */
    public function testEntityEventOldPaymentStatusIsNewPaymentStatus(array $changeSet, $methodIsExpectedToBeCalled)
    {
        $orderId = 123;
        $expectedExecutionCount = $methodIsExpectedToBeCalled ? self::once() : self::never();

        $stockServiceMock = $this->createMock(StockService::class);
        $stockServiceMock->expects($expectedExecutionCount)->method('updateOrderStocks')->with($orderId, false);

        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $unitOfWorkMock->method('getEntityChangeSet')->willReturn($changeSet);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->method('getUnitOfWork')->willReturn($unitOfWorkMock);

        $order = new Order();
        $reflection = new ReflectionObject($order);
        $reflectionProperty = $reflection->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($order, $orderId);

        $event = new Enlight_Event_EventArgs([
            'entityManager' => $entityManagerMock,
            'entity' => $order,
        ]);

        $stockSubscriber = new StockSubscriber($stockServiceMock);
        $stockSubscriber->entityEvent($event);
    }

    public static function entityEventDataProvider()
    {
        return [
            'emptyChangeSet' => [
                [],
                false,
            ],
            'old id is new id' => [
                [
                    'paymentStatus' => [
                        self::getPaymentStatus(Status::PAYMENT_STATE_COMPLETELY_PAID),
                        self::getPaymentStatus(Status::PAYMENT_STATE_COMPLETELY_PAID),
                    ],
                ],
                false,
            ],
            'old status is payment has been ordered' => [
                [
                    'paymentStatus' => [
                        self::getPaymentStatus(Status::PAYMENT_STATE_THE_PAYMENT_HAS_BEEN_ORDERED),
                        self::getPaymentStatus(Status::PAYMENT_STATE_COMPLETELY_PAID),
                    ],
                ],
                false,
            ],
            'new status is not completely paid' => [
                [
                    'paymentStatus' => [
                        self::getPaymentStatus(Status::PAYMENT_STATE_OPEN),
                        self::getPaymentStatus(Status::PAYMENT_STATE_PARTIALLY_PAID),
                    ],
                ],
                false,
            ],
            'completely paid, update stocks' => [
                [
                    'paymentStatus' => [
                        self::getPaymentStatus(Status::PAYMENT_STATE_OPEN),
                        self::getPaymentStatus(Status::PAYMENT_STATE_COMPLETELY_PAID),
                    ],
                ],
                true,
            ],
        ];
    }

    private static function getPaymentStatus($paymentStatusId)
    {
        $paymentStatus = new Status();
        $paymentStatus->setId($paymentStatusId);

        return $paymentStatus;
    }
}
