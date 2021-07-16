<?php

namespace MollieShopware\Facades\FinishCheckout;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\Order\OrderUpdater;
use MollieShopware\Components\Order\ShopwareOrderBuilder;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\Services\PaymentService;
use MollieShopware\Exceptions\MollieOrderNotFound;
use MollieShopware\Exceptions\MolliePaymentFailedException;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Exceptions\OrderStatusNotFoundException;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Facades\FinishCheckout\Models\CheckoutFinish;
use MollieShopware\Facades\FinishCheckout\Services\ConfirmationMail;
use MollieShopware\Facades\FinishCheckout\Services\MollieStatusValidator;
use MollieShopware\Facades\FinishCheckout\Services\ShopwareOrderUpdater;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Models\Order\Order;

class FinishCheckoutFacade
{

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var MollieStatusValidator
     */
    private $statusValidator;

    /**
     * @var ShopwareOrderUpdater
     */
    private $swOrderUpdater;

    /**
     * @var ShopwareOrderBuilder
     */
    private $swOrderBuilder;

    /**
     * @var MollieStatusConverter
     */
    private $statusConverter;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var ConfirmationMail
     */
    private $confirmationMail;

    /**
     * @var Config\PaymentConfigResolver
     */
    private $paymentConfig;


    /**
     * FinishCheckoutFacade constructor.
     * @param Config $config
     * @param OrderService $orderService
     * @param PaymentService $paymentService
     * @param TransactionRepository $repoTransactions
     * @param LoggerInterface $logger
     * @param MollieGatewayInterface $gwMollie
     * @param MollieStatusValidator $statusValidator
     * @param ShopwareOrderUpdater $swOrderUpdater
     * @param ShopwareOrderBuilder $swOrderBuilder
     * @param MollieStatusConverter $statusConverter
     * @param OrderUpdater $orderUpdater
     * @param ConfirmationMail $confirmationMail
     * @param Config\PaymentConfigResolver $paymentConfig
     */
    public function __construct(Config $config, OrderService $orderService, PaymentService $paymentService, TransactionRepository $repoTransactions, LoggerInterface $logger, MollieGatewayInterface $gwMollie, MollieStatusValidator $statusValidator, ShopwareOrderUpdater $swOrderUpdater, ShopwareOrderBuilder $swOrderBuilder, MollieStatusConverter $statusConverter, OrderUpdater $orderUpdater, ConfirmationMail $confirmationMail, Config\PaymentConfigResolver $paymentConfig)
    {
        $this->config = $config;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->repoTransactions = $repoTransactions;
        $this->logger = $logger;
        $this->gwMollie = $gwMollie;
        $this->statusValidator = $statusValidator;
        $this->swOrderUpdater = $swOrderUpdater;
        $this->swOrderBuilder = $swOrderBuilder;
        $this->statusConverter = $statusConverter;
        $this->orderUpdater = $orderUpdater;
        $this->confirmationMail = $confirmationMail;
        $this->paymentConfig = $paymentConfig;
    }


    /**
     * @param $transactionId
     * @return CheckoutFinish
     * @throws ApiException
     * @throws MollieOrderNotFound
     * @throws MolliePaymentFailedException
     * @throws OrderNotFoundException
     * @throws TransactionNotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Enlight_Event_Exception
     * @throws \MollieShopware\Exceptions\MolliePaymentNotFound
     * @throws \MollieShopware\Exceptions\PaymentStatusNotFoundException
     */
    public function finishTransaction($transactionId)
    {
        $transaction = $this->repoTransactions->find($transactionId);

        if (!$transaction instanceof Transaction) {
            throw new TransactionNotFoundException($transactionId);
        }

        # -------------------------------------------------------------------------------------------------------------
        # VALIDATE PAYMENT STATUS VALUES

        /** @var null|\Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = null;

        /** @var null|Payment $molliePayment */
        $molliePayment = null;


        # we start by validating our order or payment with Mollie.
        # if this is not valid, we immediately stop any further processing
        if ($transaction->isTypeOrder()) {
            $mollieOrder = $this->gwMollie->getOrder($transaction->getMollieOrderId());

            if (!$this->statusValidator->didOrderCheckoutSucceed($mollieOrder)) {
                throw new MolliePaymentFailedException($mollieOrder->id, 'The payment failed. Please see the Mollie Dashboard for more!');
            }
        } else {
            $molliePayment = $this->gwMollie->getPayment($transaction->getMolliePaymentId());

            if (!$this->statusValidator->didPaymentCheckoutSucceed($molliePayment)) {
                throw new MolliePaymentFailedException($molliePayment->id, 'The payment failed. Please see the Mollie Dashboard for more!');
            }
        }

        # -------------------------------------------------------------------------------------------------------------
        # FINAL TRANSACTION NUMBER

        # get the real transaction number
        # which is either tr_xxxx or ord_xxxx depending on the type
        # of payment and API we have used
        $transactionNumber = $transaction->getShopwareTransactionNumber();

        if ($transaction->isTypeOrder()) {
            $finalTransactionNumber = $this->swOrderUpdater->getFinalTransactionIdFromOrder($mollieOrder);
        } else {
            $finalTransactionNumber = $this->swOrderUpdater->getFinalTransactionIdFromPayment($molliePayment);
        }


        # our payment was successful!
        # now we need to check if our order needs to be created after the payment (plugin configuration).
        # if so, verify if our session needs to be restored, and then just create the Shopware order.
        $orderCreation = $this->paymentConfig->getFinalOrderCreation(
            $transaction->getPaymentMethod(),
            $transaction->getShopId()
        );

        if ($orderCreation === OrderCreationType::AFTER_PAYMENT) {
            # create an order in shopware
            $orderNumber = $this->swOrderBuilder->createOrderAfterPayment(
                $transactionNumber,
                $finalTransactionNumber,
                $this->config->isPaymentStatusMailEnabled(),
                $transaction->getBasketSignature()
            );

            # update the order number in our transaction or the upcoming steps
            # and immediately save it in case of upcoming errors
            $transaction->setOrderNumber($orderNumber);
            $this->repoTransactions->save($transaction);
        }

        # -------------------------------------------------------------------------------------------------------------

        # now load the order number from the transaction
        # and also load our shopware order
        $orderNumber = $transaction->getOrderNumber();
        $swOrder = $this->orderService->getShopwareOrderByNumber($orderNumber);

        if (!$swOrder instanceof Order) {
            $this->logger->critical('Warning, Mollie is paid but no order exists in Shopware for transaction ' . $transactionId);
            throw new OrderNotFoundException('Order with number: ' . $orderNumber . ' not found!');
        }

        # make sure our transaction is correctly linked to the order
        $transaction->setOrderId($swOrder->getId());
        $this->repoTransactions->save($transaction);


        # if we have created our order before the payment
        # then we have to update the transaction ID here so that
        # it will be our final transaction number
        if ($orderCreation === OrderCreationType::BEFORE_PAYMENT) {
            $this->swOrderUpdater->updateTransactionId($swOrder, $finalTransactionNumber);
        }


        # now we need to update the transaction identifier in the Shopware order.
        # this will be a number from Mollie depending on some settings.
        # we either extract that data from the Mollie Order or Mollie Payment
        if ($transaction->isTypeOrder()) {
            $this->swOrderUpdater->updateReferencesFromMollieOrder($swOrder, $mollieOrder, $transaction);

            # if we have a separate order entry in Mollie
            # make sure we update its number with the one from Shopware
            $mollieOrder->orderNumber = (string)$orderNumber;
            $mollieOrder->update();
        }

        # -------------------------------------------------------------------------------------------------------------
        # UPDATE the actual payment and order status in Shopware
        # by using the status from the Mollie API object.
        # please note, the payment/order is loaded again from Mollie! we would actually have it
        # but I'm not quite sure if its better to reload it again from the server due to some changes above.
        if ($transaction->isTypeOrder()) {
            $mollieOrder = $this->paymentService->getMollieOrder($swOrder, $transaction);
            $mollieStatus = $this->statusConverter->getMollieOrderStatus($mollieOrder);
        } else {
            $molliePayment = $this->paymentService->getMolliePayment($swOrder, $transaction);
            $mollieStatus = $this->statusConverter->getMolliePaymentStatus($molliePayment);
        }


        # update the payment status of our shopware order
        $this->orderUpdater->updateShopwarePaymentStatus(
            $swOrder,
            $mollieStatus
        );


        # update the order status of our shopware order
        # if configured to do this
        if ($this->config->updateOrderStatus()) {
            try {
                $this->orderUpdater->updateShopwareOrderStatus(
                    $swOrder,
                    $mollieStatus
                );
            } catch (OrderStatusNotFoundException $ex) {
                # if we have a problem here, we will still continue
                # with sending order confirmations.
                # but at least we will log that the status wasn't updated
                $this->logger->warning(
                    'The status of order: ' . $swOrder->getNumber() . ' has not been updated to: ' . $mollieStatus,
                    [
                        'error' => $ex->getMessage()
                    ]
                );
            }
        }


        # if we have created the order before this
        # then send the order confirmation mail NOW,
        # if the mollie payment is valid
        if ($orderCreation === OrderCreationType::BEFORE_PAYMENT && PaymentStatus::isApprovedStatus($mollieStatus)) {
            try {

                $this->confirmationMail->sendConfirmationEmail($transaction);

            } catch (\Exception $ex) {
                # never ever break if only an email cannot be sent
                # lets just add a log here.
                $this->logger->warning(
                    'Problem when sending confirmation email for order: ' . $swOrder->getNumber(),
                    [
                        'error' => $ex->getMessage()
                    ]
                );
            }
        }

        return new CheckoutFinish(
            $swOrder->getNumber(),
            $swOrder->getTemporaryId()
        );
    }

    /**
     * @param $transactionNumber
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function cleanupTransaction($transactionNumber)
    {
        $transaction = $this->repoTransactions->find($transactionNumber);

        if (!$transaction instanceof Transaction) {
            return;
        }

        # Unset OrdermailVariables to prevent bloating transaction table
        if ($transaction->getOrdermailVariables() !== null) {
            $transaction->setOrdermailVariables(null);
            $this->repoTransactions->save($transaction);
        }
    }

}
