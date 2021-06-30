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
        if (!$this->isOrderSessionExisting()) {

            $this->logger->notice('Missing Session! Restoring Session for Transaction: ' . $transaction->getId());

            $this->logger->debug('Restoring to SessionID ' . $transaction->getSessionId() . ' again');

            $select = Shopware()->Db()->select();
            $select->from('s_core_sessions');
            $select->where('id = :id');
            $select->bind([
                    'id' => $transaction->getSessionId(),
                ]
            );

            $data = Shopware()->Db()->fetchAll($select);

            if (count($data) > 0) {
                $sessionRow = $data[0];
                $this->logger->debug('Existing Session Row ' . $sessionRow['id'] . ', Modified: ' . $sessionRow['modified'] . ', Expiry: ' . $sessionRow['expiry']);
            } else {
                $this->logger->debug('No Existing Session Row for ID: ' . $transaction->getSessionId());
            }

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
    public function isOrderSessionExisting()
    {
        $variables = Shopware()->Session()->sOrderVariables;

        $sessionPaymentId = (string)$variables['sUserData']['additional']['user']['paymentID'];
        $userLoggedIn = (string)$variables['sUserLoggedIn'];

        $loginID = Shopware()->Session()->get('sUserId');

        if (empty($sessionPaymentId)) {

            $this->logger->debug('No session due to missing paymentID, sUserId: ' . $loginID);

            return false;
        }

        if (empty($userLoggedIn)) {

            $this->logger->debug('No session due to missing sUserLoggedIn, sUserId: ' . $loginID);

            return false;
        }

        return true;
    }

}
