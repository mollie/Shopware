<?php

namespace MollieShopware\Facades\FinishCheckout;

use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Exceptions\OrderNotFoundException;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Models\Order\Order;

class RestoreSessionFacade
{

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * RestoreSessionFacade constructor.
     * @param TransactionRepository $repoTransactions
     * @param SessionManager $sessionManager
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(TransactionRepository $repoTransactions, SessionManager $sessionManager, OrderService $orderService, LoggerInterface $logger)
    {
        $this->repoTransactions = $repoTransactions;
        $this->sessionManager = $sessionManager;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }


    /**
     * @param $transactionID
     * @param $requestPaymentToken
     * @throws OrderNotFoundException
     * @throws TransactionNotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function tryRestoreSession($transactionID, $requestPaymentToken)
    {
        $transaction = $this->repoTransactions->find($transactionID);

        if (!$transaction instanceof Transaction) {
            throw new TransactionNotFoundException($transactionID);
        }

        # try to restore our session if our
        # current session is empty
        if (!$this->isOrderSessionExisting()) {
            $this->tryRestore($transaction, $requestPaymentToken);
        }

        # always make sure to clear if we have either restored our session
        # or if our session did exist anyway.
        # its a one-time token
        $this->sessionManager->deleteSessionToken($transaction);
    }


    /**
     * @param Transaction $transaction
     * @param $requestPaymentToken
     * @throws OrderNotFoundException
     * @throws \MollieShopware\Components\SessionManager\Exceptions\InvalidSessionTokenException
     */
    private function tryRestore(Transaction $transaction, $requestPaymentToken)
    {
        # load our pending order from the session of the transaction.
        # this is always only 1 order
        $pendingOrder = $this->orderService->getOrderBySessionId($transaction->getSessionId());

        if (!$pendingOrder instanceof Order) {
            throw new OrderNotFoundException('Pending Order for Session ' . $transaction->getSessionId() . ' not found!');
        }

        # we do only restore a session if that order number is "0"
        # and thus, has not been completed yet!
        if ((string)$pendingOrder->getNumber() !== '0') {
            throw new \Exception('Not allowed to restore Session for completed order!');
        }

        $this->logger->notice('Missing Session! Restoring Session for Transaction: ' . $transaction->getId());

        $this->sessionManager->restoreFromToken($transaction, $requestPaymentToken);
    }

    /**
     * Gets if a session exists that contains order variables.
     * If not, we need to restore our order variables in our session.
     *
     * @return bool
     */
    public function isOrderSessionExisting()
    {
        $variables = Shopware()->Session()->sOrderVariables;

        $sessionPaymentId = (string)$variables['sUserData']['additional']['user']['paymentID'];
        $userLoggedIn = (string)$variables['sUserLoggedIn'];

        if (empty($sessionPaymentId)) {
            return false;
        }

        if (empty($userLoggedIn)) {
            return false;
        }

        return true;
    }

}
