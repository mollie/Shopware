<?php

namespace MollieShopware\Facades\FinishCheckout;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use MollieShopware\Components\Config;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\Helpers\MollieStatusConverter;
use MollieShopware\Components\Mollie\Builder\Payment\DescriptionBuilder;
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
use MollieShopware\Services\Mollie\Payments\Extractor\PaymentFailedDetailExtractor;
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
     * @var PaymentFailedDetailExtractor
     */
    private $paymentFailedDetailExtractor;


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
    public function __construct(Config $config, OrderService $orderService, PaymentService $paymentService, TransactionRepository $repoTransactions, LoggerInterface $logger, MollieGatewayInterface $gwMollie, MollieStatusValidator $statusValidator, ShopwareOrderUpdater $swOrderUpdater, ShopwareOrderBuilder $swOrderBuilder, MollieStatusConverter $statusConverter, OrderUpdater $orderUpdater, ConfirmationMail $confirmationMail, Config\PaymentConfigResolver $paymentConfig, PaymentFailedDetailExtractor $paymentFailedDetailExtractor)
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
        $this->paymentFailedDetailExtractor = $paymentFailedDetailExtractor;
    }


    /**
     * @param $transactionId
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
     * @return CheckoutFinish
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
                $details = $this->paymentFailedDetailExtractor->extractDetails($molliePayment);
                $exception = new MolliePaymentFailedException($molliePayment->id, 'The payment failed. Please see the Mollie Dashboard for more!');
                if ($details) {
                    $exception->setFailedDetails($details);
                }
                throw $exception;
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

        # let's store our payment-REF id in the separate field.
        # we need this for order lookups, e.g. in email subscribers.
        # that's why it also needs to be BEFORE creating an order below!
        if ($transaction->getMolliePaymentId() !== $finalTransactionNumber && $transaction->getMollieOrderId() !== $finalTransactionNumber) {
            $transaction->setMolliePaymentRefId($finalTransactionNumber);
            $this->repoTransactions->save($transaction);
        }


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
            # also mark our confirmation mail to be sent, just to have it "complete" and accurate
            $transaction->setConfirmationMailSent(true);

            $this->repoTransactions->save($transaction);
        }


        $orderNumber = $transaction->getOrderNumber();
        $swOrder = $this->orderService->getShopwareOrderByNumber($orderNumber);

        if (!$swOrder instanceof Order) {
            $this->logger->critical('Warning, Mollie is paid but no order exists in Shopware for transaction ' . $transactionId);
            throw new OrderNotFoundException('Order with number: ' . $orderNumber . ' not found!');
        }

        # -------------------------------------------------------------------------------------------------------------
        # -------------------------------------------------------------------------------------------------------------
        # SAFETY CHECKPOINT
        # if we have errors from here on, then we MUST NOT throw additional exceptions.
        # these would lead to a cancelled order, but we do not want the customer to see that something was cancelled
        # if the overall payment did succeed and the order was successfully created
        # -------------------------------------------------------------------------------------------------------------
        # -------------------------------------------------------------------------------------------------------------

        try {

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
            }

            # if our shopware order has just been created, then mollie does not yet know the correct numbers.
            # In this case, we try to update everything inside Mollie to match the Shopware order data.
            if ($orderCreation === OrderCreationType::AFTER_PAYMENT) {

                # TODO this is not perfect, and should be changed one day. there might be prefixes based on templates anyway one day
                $descriptionBuilder = new DescriptionBuilder();
                $newDescription = $descriptionBuilder->buildDescription($transaction, '');

                if ($transaction->isTypeOrder()) {
                    # update ORDER
                    $this->gwMollie->updateOrderNumber($mollieOrder->id, $newDescription);
                    # update PAYMENT
                    $tmpPayment = $mollieOrder->_embedded->payments[0];
                    $this->gwMollie->updatePaymentDescription($tmpPayment->id, $newDescription);
                } else {
                    $this->gwMollie->updatePaymentDescription($molliePayment->id, $newDescription);
                }
            }

            # -------------------------------------------------------------------------------------------------------------
            # UPDATE the actual payment and order status in Shopware
            # by using the status from the Mollie API object.
            # please note, the payment/order is loaded again from Mollie! we would actually have it,
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
            # if the mollie payment is valid.

            if ($orderCreation === OrderCreationType::BEFORE_PAYMENT && PaymentStatus::isApprovedStatus($mollieStatus)) {

                # also check for multiple returns on this page.
                # only send the first time, as long as the mail has not been sent.
                $isFirstRequest = (string)$transaction->getOrdermailVariables() !== '';

                if ($isFirstRequest) {
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
            }
        } catch (\Exception $ex) {
            $this->logger->warning(
                'Attention, something went wrong during payment of order: ' . $swOrder->getNumber(),
                [
                    'info' => 'The checkout continued as normal for the user, but some data could not be updated in Shopware or Mollie!',
                    'error' => $ex->getMessage()
                ]
            );
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
