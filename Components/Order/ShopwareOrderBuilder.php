<?php

namespace MollieShopware\Components\Order;

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
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param Shopware_Controllers_Frontend_Payment $controller
     * @param LoggerInterface $logger
     */
    public function __construct(Shopware_Controllers_Frontend_Payment $controller, LoggerInterface $logger)
    {
        $this->controller = $controller;
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
     * @param Transaction $transaction
     * @param $sendPaymentStatusMail
     * @return false|int
     */
    public function createOrderAfterPayment(Transaction $transaction, $sendPaymentStatusMail)
    {
        $transactionNumber = $transaction->getShopwareTransactionNumber();

        $this->logger->debug('Create new Order after payment for Transaction ' . $transactionNumber);

        $orderNumber = $this->controller->saveOrder(
            $transactionNumber,
            $transaction->getBasketSignature(),
            Status::PAYMENT_STATE_OPEN,
            $sendPaymentStatusMail
        );

        return $orderNumber;
    }

}
