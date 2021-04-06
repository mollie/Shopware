<?php

namespace MollieShopware\Components\SessionSnapshot;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\SessionSnapshot\Exceptions\InvalidSessionHashException;
use MollieShopware\Models\SessionSnapshot\Repository as SnapshotRepository;
use MollieShopware\Models\SessionSnapshot\SessionSnapshot;
use MollieShopware\Models\Transaction;
use Shopware\Models\Order\Basket;
use Shopware\Models\Order\Repository;
use Symfony\Component\HttpFoundation\Cookie;

class SessionSnapshotManager
{

    /**
     * @var SessionHashGeneratorInterface
     */
    private $hashGenerator;

    /**
     * @var SnapshotRepository
     */
    private $repoSnapshots;

    /**
     * @var Connection
     */
    private $connection;


    /**
     * SessionSnapshotManager constructor.
     * @param SessionHashGeneratorInterface $hashGenerator
     * @param EntityManager $entityManager
     */
    public function __construct(SessionHashGeneratorInterface $hashGenerator, EntityManager $entityManager)
    {
        $this->hashGenerator = $hashGenerator;
        $this->repoSnapshots = $entityManager->getRepository(SessionSnapshot::class);
        $this->connection = $entityManager->getConnection();
    }


    /**
     * @return array|SessionSnapshot[]|object[]
     */
    public function findAllSnapshots()
    {
        return $this->repoSnapshots->findAll();
    }

    /**
     * @param int $transactionId
     * @return ?SessionSnapshot
     */
    public function findSnapshot($transactionId)
    {
        return $this->repoSnapshots->findByTransactionId($transactionId);
    }

    /**
     * @param $transactionId
     * @return SessionSnapshot
     */
    public function buildSnapshot($transactionId)
    {
        $sessionId = $this->getSession()->get('sessionId');
        $sessionVariables = $this->getSession()->getIterator();

        $serializedVariables = serialize($sessionVariables);

        $snapshot = new SessionSnapshot();
        $snapshot->setTransactionId($transactionId);

        $snapshot->setSessionId($sessionId);
        $snapshot->setVariables($serializedVariables);

        $snapshot->setHash($this->hashGenerator->generateHash());

        return $snapshot;
    }

    /**
     * @param SessionSnapshot $snapshot
     * @param string $sessionHash
     * @throws InvalidSessionHashException
     */
    public function restoreSnapshot(SessionSnapshot $snapshot, $sessionHash, \Enlight_Controller_Action $controller)
    {
        # first validate if our hashes match
        # do ONLY restore if the correct hash has been provided
        if ($snapshot->getHash() !== $sessionHash) {
            # TODO add auto delete on x failures
            throw new InvalidSessionHashException('Invalid hash when restoring snapshot: ' . $snapshot->getId() . ', ' . $sessionHash);
        }

        $newSessionId = $this->getSession()->get('sessionId');

        $objectSession = unserialize($snapshot->getVariables());

        # iterate through every single shopware key
        # and make sure to correctly set it again
        foreach ($objectSession as $key => $value) {
        #    $this->getSession()->offsetSet($key, $value);
        }

        $previousSessionId = $snapshot->getSessionId();


        $cookie = new Cookie(
            'session-1',
            $previousSessionId,
            0,
            ini_get('session.cookie_path'),
            null,
            true # todo
        );


        $controller->Response()->headers->setCookie($cookie);


        # we also have to update the s_basket entry
        # otherwise it would not show products if we come
        # back to the cart on failed payments after session restoring
        $qb = $this->connection->createQueryBuilder();

        $qb->update('s_order_basket')
            ->set('sessionID', ':newSessionID')
            ->where($qb->expr()->eq('sessionID', ':oldSessionID'))
            ->setParameter(':newSessionID', $newSessionId)
            ->setParameter(':oldSessionID', $previousSessionId);

        # $qb->execute();
    }

    /**
     * @param SessionSnapshot $snapshot
     */
    public function delete(SessionSnapshot $snapshot)
    {
        $this->repoSnapshots->delete($snapshot);
    }

    /**
     * Gets if a session exists that contains order variables.
     * If not, we need to restore our order variables in our session.
     *
     * @return bool
     */
    public function isOrderSessionExisting()
    {
        $variables = $this->getSession()->sOrderVariables;

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

    /**
     * @return \Enlight_Components_Session_Namespace
     */
    private function getSession()
    {
        # do this here, won't work in the constructor
        return Shopware()->Session();
    }

}
