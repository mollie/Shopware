<?php

namespace MollieShopware\Components\Order;

use MollieShopware\Components\Services\OrderService;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Models\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Models\Order\Status;
use Shopware_Controllers_Frontend_Payment;

class ShopwareOrderBuilder
{

    /**
     * @var Shopware_Controllers_Frontend_Payment
     */
    private $controller;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * ShopwareOrderBuilder constructor.
     * @param Shopware_Controllers_Frontend_Payment $controller
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(Shopware_Controllers_Frontend_Payment $controller, OrderService $orderService, LoggerInterface $logger)
    {
        $this->controller = $controller;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }


    /**
     * @param $transactionID
     * @param $basketSignature
     * @return false|int
     */
    public function createOrderBeforePayment($transactionID, $basketSignature)
    {
        $this->logger->debug('Create new Order before payment for Transaction ' . $transactionID);

        $orderNumber = $this->controller->saveOrder(
            $transactionID,
            $basketSignature,
            Status::PAYMENT_STATE_OPEN,
            false
        );

        return $orderNumber;
    }

    /**
     * @param $originalTransactionNumber
     * @param $finalTransactionNumber
     * @param $sendPaymentStatusMail
     * @param $basketSignature
     * @return false|int
     */
    public function createOrderAfterPayment($originalTransactionNumber, $finalTransactionNumber, $sendPaymentStatusMail, $basketSignature)
    {
        $this->logger->debug('Create new Order after payment for Transaction ' . $originalTransactionNumber);

        try {

            # if the redirect URL is somehow called multiple times, or just "again" some time,
            # we have to avoid that new orders get created.
            # This is also solved by using the correct transactionNumber in the Shopware order.
            # So Shopware itself will not create it multiple times - that's why we just continue with everything.
            # Still, we want to log that the URL has been called again.
            $existingOrder = $this->orderService->getOrderByTransactionId($finalTransactionNumber);

            $this->logger->warning(
                'Order is already existing for ' . $originalTransactionNumber . ' because Redirect-URL is called again!',
                array(
                    'error' => array(
                        'reason' => 'This usually happens if the Mollie Redirect URL is called multiple times instead of just once! Then the order is only created the first time. It should not happen, but doesnt break anything!',
                        'solution' => 'Please verify why the user got sent back from Mollie to your shop again!',
                    ),
                    'data' => array(
                        'orderID' => $existingOrder->getId(),
                        'orderNumber' => $existingOrder->getNumber(),
                        'mollieTransaction' => $originalTransactionNumber,
                        'shopwareTransaction' => $finalTransactionNumber,
                    ),
                )
            );

            # attention, lets reuse this order number,
            # if we would continue with saveOrder, its not duplicated anymore (because the transaction number is correct now)
            # but there would always be another confirmation email being sent :(
            return $existingOrder->getNumber();

        } catch (OrderNotFoundException $ex) {
            # if we have no order, we can continue
            # by creating our new one
        }

        return $this->controller->saveOrder(
            $finalTransactionNumber,
            $basketSignature,
            Status::PAYMENT_STATE_OPEN,
            $sendPaymentStatusMail
        );
    }

}
