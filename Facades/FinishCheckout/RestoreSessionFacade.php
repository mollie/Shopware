<?php

namespace MollieShopware\Facades\FinishCheckout;

use MollieShopware\Components\Services\OrderService;
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Exceptions\TransactionNotFoundException;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param TransactionRepository $repoTransactions
     * @param SessionManager $sessionManager
     * @param LoggerInterface $logger
     */
    public function __construct(TransactionRepository $repoTransactions, SessionManager $sessionManager, LoggerInterface $logger)
    {
        $this->repoTransactions = $repoTransactions;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
    }


    /**
     * @param $transactionID
     * @param $requestPaymentToken
     * @throws TransactionNotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \MollieShopware\Components\SessionManager\Exceptions\InvalidSessionTokenException
     */
    public function tryRestoreSession($transactionID, $requestPaymentToken)
    {
        $transaction = $this->repoTransactions->find($transactionID);

        if (!$transaction instanceof Transaction) {
            throw new TransactionNotFoundException($transactionID);
        }

        # try to restore our session if our current session is empty
        if (!$this->isUserSessionExisting()) {

            $this->logger->notice('Missing Session! Restoring Session for Transaction: ' . $transaction->getId());

            $this->sessionManager->restoreFromToken($transaction, $requestPaymentToken);
        }

        # always make sure to clear if we have either restored our session
        # or if our session did exist anyway.
        # its a one-time token
        $this->sessionManager->deleteSessionToken($transaction);
    }

    /**
     * Gets if a session exists that contains order variables.
     * If not, we need to restore our order variables in our session.
     *
     * @return bool
     */
    public function isUserSessionExisting()
    {
        if (Shopware()->Session()->get('sUserId')) {
            return true;
        }

        return false;
    }

}
