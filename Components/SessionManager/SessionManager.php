<?php

namespace MollieShopware\Components\SessionManager;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use MollieShopware\Components\SessionManager\Exceptions\InvalidSessionTokenException;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepository;
use Shopware\Recovery\Common\DependencyInjection\ContainerInterface;

class SessionManager implements SessionManagerInterface
{

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Connection
     */
    private $connection;

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
     * SessionManager constructor.
     * @param $container
     * @param EntityManager $entityManager
     * @param Connection $connection
     * @param CookieRepositoryInterface $cookieRepository
     * @param TokenGeneratorInterface $tokenGenerator
     */
    public function __construct($container, EntityManager $entityManager, Connection $connection, CookieRepositoryInterface $cookieRepository, TokenGeneratorInterface $tokenGenerator)
    {
        $this->container = $container;
        $this->repoCookies = $cookieRepository;
        $this->connection = $connection;
        $this->tokenGenerator = $tokenGenerator;

        $this->repoTransactions = $entityManager->getRepository(Transaction::class);
    }


    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->container->get('sessionid');
    }

    /**
     * @param $days
     * @throws \Doctrine\DBAL\Exception
     */
    public function extendSessionLifespan($days)
    {
        $session = $this->container->get('session');

        # write session data and commit database transaction to avoid locks
        # @see Shopware\Components\Session\PdoSessionHandler::close()
        if (method_exists($session, 'save')) {
            $session->save();
        }

        session_write_close();


        $sessionId = $this->getSessionId();
        $lifetimeSeconds = $days * 24 * 60 * 60;

        ini_set('session.gc_maxlifetime', $lifetimeSeconds);

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        $qb->update('s_core_sessions')
            ->set('expiry', ':expiry')
            ->where($qb->expr()->eq('id', ':id'))
            ->setParameter(':expiry', time() + $lifetimeSeconds)
            ->setParameter(':id', $sessionId);

        $qb->execute();
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
