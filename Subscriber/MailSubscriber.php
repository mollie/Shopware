<?php

namespace MollieShopware\Subscriber;


use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Mollie\Api\Resources\Payment;
use MollieShopware\Components\Constants\PaymentMethod;
use MollieShopware\Components\Services\OrderService;
use MollieShopware\Gateways\MollieGatewayInterface;
use MollieShopware\Models\Mails\BankTransferMailData;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use MollieShopware\MollieShopware;
use Psr\Log\LoggerInterface;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;


class MailSubscriber implements SubscriberInterface
{

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionRepository $transactionRepo
     */
    private $transactionRepo;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Order_SendMail_FilterContext' => 'onPrepareMailVariables',
            'Shopware_Modules_Order_SendMail_Send' => 'onSendMail',
        ];
    }


    /**
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @param MollieGatewayInterface $gatewayMollie
     * @throws \Exception
     */
    public function __construct(OrderService $orderService, LoggerInterface $logger, MollieGatewayInterface $gatewayMollie)
    {
        $this->orderService = $orderService;
        $this->logger = $logger;
        $this->gwMollie = $gatewayMollie;

        $this->orderService = Shopware()->Container()->get('mollie_shopware.order_service');
        $this->transactionRepo = Shopware()->Models()->getRepository(Transaction::class);
    }


    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onPrepareMailVariables(Enlight_Event_EventArgs $args)
    {
        $variables = $args->getReturn();

        try {

            $orderNumber = (string)$variables['sOrderNumber'];
            $paymentName = (string)$variables['additional']['payment']['name'];

            $bankData = $this->getBankTransferVariables($paymentName, $orderNumber);

            $variables['Mollie'] = [
                'bank' => $bankData->toArray(),
            ];

        } catch (\Exception $e) {

            # we catch exceptions here to avoid that orders fail,
            # only because of broken email data
            $this->logger->warning(
                'Error when preparing email variables for Mollie: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        $args->setReturn($variables);
    }

    /**
     * Catch mail variables when the confirmation email is triggered and store
     * them in the database to use them when the order is fully processed.
     *
     * @param Enlight_Event_EventArgs $args
     * @return false|void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onSendMail(Enlight_Event_EventArgs $args)
    {
        $variables = $args->get('variables');

        if ($variables === null) {
            return;
        }

        $orderNumber = (isset($variables['ordernumber']) ? $variables['ordernumber'] : null);

        if (empty($orderNumber)) {
            return;
        }


        /** @var Order $order */
        $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

        if (!$order instanceof Order) {
            return;
        }


        # skip all non-mollie orders, or mollie orders that are already completed
        # so we only process new ones in here
        if (!$this->startsWith($order->getTransactionId(), 'mollie_')) {
            return;
        }

        # we only modify the initial order confirmation.
        # only process OPEN order mails and skip all others
        if ($order->getPaymentStatus()->getId() !== Status::PAYMENT_STATE_OPEN) {
            return;
        }


        /** @var Transaction $transaction */
        $transaction = $this->transactionRepo->findOneBy(['transactionId' => $order->getTransactionId()]);

        if (!$transaction instanceof Transaction) {
            return;
        }

        # if already filled, skip
        if (!empty($transaction->getOrdermailVariables())) {
            return;
        }


        try {

            $transaction->setOrdermailVariables(json_encode($variables));
            $this->transactionRepo->save($transaction);

        } catch (\Exception $e) {
            $this->logger->error(
                'Error in onSendMail event',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        return false;
    }

    /**
     * @param $text
     * @param $searchTerm
     * @return bool
     */
    private function startsWith($text, $searchTerm)
    {
        if (strpos($text, $searchTerm) === 0) {
            return true;
        }
        return false;
    }

    /**
     * @param string $paymentName
     * @param string $orderNumber
     * @return BankTransferMailData
     */
    private function getBankTransferVariables($paymentName, $orderNumber)
    {
        # if it's not even a bank transfer, then
        # simply onl return the base data "exists" false
        # but that should at least always exist
        if ($paymentName !== MollieShopware::PAYMENT_PREFIX . PaymentMethod::BANKTRANSFER) {
            return new BankTransferMailData(false, '', '', '', '');
        }


        /** @var Order $order */
        $order = $this->orderService->getShopwareOrderByNumber($orderNumber);

        if (!$order instanceof Order) {
            return new BankTransferMailData(false, '', '', '', '');
        }

        /** @var Transaction $transaction */
        $transaction = $this->transactionRepo->findOneBy(['orderId' => $order->getId()]);

        if (!$transaction instanceof Transaction) {
            return new BankTransferMailData(false, '', '', '', '');
        }


        if ($transaction->isTypeOrder()) {
            $mollieOrder = $this->gwMollie->getOrder($transaction->getMollieOrderId());
            $molliePayment = $mollieOrder->_embedded->payments[0];
        } else {
            $molliePayment = $this->gwMollie->getPayment($transaction->getMolliePaymentId());
        }


        if (!$molliePayment instanceof \stdClass && !$molliePayment instanceof Payment) {
            return new BankTransferMailData(false, '', '', '', '');
        }

        if ($molliePayment->details === null) {
            return new BankTransferMailData(false, '', '', '', '');
        }


        $bankName = (string)$molliePayment->details->bankName;
        $bankAccount = (string)$molliePayment->details->bankAccount;
        $bankBic = (string)$molliePayment->details->bankBic;
        $transferReference = (string)$molliePayment->details->transferReference;

        return new BankTransferMailData(true, $bankName, $bankAccount, $bankBic, $transferReference);
    }
}
