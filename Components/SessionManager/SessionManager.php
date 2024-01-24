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
        # ATTENTION!
        # this works most of the time, but not all the time!
        # it causes the finish-page to have missing data, like missing address.
        # it does not make any sense, but its a deep logic of shopware or the session handling
        # and we have to remove it for now and stick with the default server and system configuration.
    }

    /**
     * @param Transaction $transaction
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @return string
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
