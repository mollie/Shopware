<?php

namespace MollieShopware\Components\SessionManager;

use Doctrine\ORM\EntityManager;
use MollieShopware\Components\SessionManager\Exceptions\InvalidSessionTokenException;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;

class SessionManager
{

    /**
     * @var CookieRepositoryInterface
     */
    private $repoCookies;

    /**
     * @var TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var TokenGeneratorInterface
     */
    private $tokenGenerator;


    /**
     * SessionSnapshotManager constructor.
     * @param EntityManager $entityManager
     * @param CookieRepositoryInterface $cookieRepository
     * @param TokenGeneratorInterface $tokenGenerator
     */
    public function __construct(EntityManager $entityManager, CookieRepositoryInterface $cookieRepository, TokenGeneratorInterface $tokenGenerator)
    {
        $this->repoCookies = $cookieRepository;
        $this->tokenGenerator = $tokenGenerator;

        $this->repoTransactions = $entityManager->getRepository(Transaction::class);
    }


    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function generateSessionToken(Transaction $transaction)
    {
        $token = $this->tokenGenerator->generateToken();

        $transaction->setSessionToken($token);

        $this->repoTransactions->save($transaction);

        return $token;
    }

    /**
     * @param Transaction $transaction
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function deleteSessionToken(Transaction $transaction)
    {
        $transaction->setSessionToken('');

        $this->repoTransactions->save($transaction);
    }

    /**
     * @param Transaction $transaction
     * @param string $requestSessionToken
     * @throws InvalidSessionTokenException
     */
    public function restoreFromToken(Transaction $transaction, $requestSessionToken)
    {
        # if token is empty, don't allow restoring anything
        if ((string)$requestSessionToken === '') {
            throw new InvalidSessionTokenException(
                'Empty token when restoring transaction: ' . $transaction->getId()
            );
        }

        # first validate if our hashes match
        # do ONLY restore if the correct hash has been provided
        if ((string)$transaction->getSessionToken() !== (string)$requestSessionToken) {
            throw new InvalidSessionTokenException(
                'Invalid token when restoring transaction: ' . $transaction->getId() . ', ' . $requestSessionToken
            );
        }

        $this->repoCookies->setSessionCookie($transaction->getSessionId());
    }

}
