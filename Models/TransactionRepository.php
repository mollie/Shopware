<?php

	// Mollie Shopware Plugin Version: 1.1.0.4

namespace MollieShopware\Models;

use Shopware\Components\Model\ModelRepository;
use MollieShopware\Models\Transaction;
use MollieShopware\Components\Constants\PaymentStatus;
use Exception;
use DateTime;

class TransactionRepository extends ModelRepository
{
    /**
     * Initialize a new Transaction
     *
     * @param  string $quoteNumber
     * @param  float  $amount
     * @param  string $currency
     * @param  string $token
     * @param  string $signature
     * @return Transaction
     */
    public function createNew($orderId)
    {
        $now = new DateTime;

        $transaction = new Transaction;

        $transaction->setSessionId($orderId);
        $transaction->setUserId(0);
        $transaction->setPaymentId(0);

//        $transaction->setSessionId(session_id());
//
//        $transaction->setQuoteNumber($quoteNumber);
//        $transaction->setAmount($amount);
//        $transaction->setCurrency($currency);
//        $transaction->setToken($token);
//        $transaction->setSignature($signature);

        $transaction->setCreatedAt($now);
        $transaction->setUpdatedAt($now);

        $this->save($transaction);

        return $transaction;
    }

    /**
     * Update the status on a Transaction
     *
     * @param  Transaction $transaction
     * @param  int         $status
     * @return Transaction
     */
    public function updateStatus(Transaction $transaction, $status)
    {
        if (PaymentStatus::isPaymentStatus($status)) {
            $transaction->setStatus($status);
            return $this->save($transaction);
        }

        throw new Exception("{$status} is not a valid PaymentStatus");
    }

    /**
     * Update the orderId on a Transaction
     *
     * @param  Transaction $transaction
     * @param  int         $orderId
     * @return Transaction
     */
    public function updateOrderNumber(Transaction $transaction, $orderNumber)
    {
        $transaction->setOrderNumber($orderNumber);
        return $this->save($transaction);
    }

    public function addException(Transaction $transaction, Exception $exception)
    {
        $exceptions = $transaction->getExceptions();

        $exceptions[] = [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTrace(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        $transaction->setExceptions($exceptions);
        return $this->save($transaction);
    }

    /**
     * Get a Transaction by quoteNumber
     *
     * @param  string $quoteNumber
     * @return Transaction
     */
    public function getByQuoteNumber($quoteNumber)
    {
        return $this->getEntityManager()
            ->getRepository(Transaction::class)
            ->findOneBy([ 'quoteNumber' => $quoteNumber ], [ 'createdAt' => 'DESC' ]);
    }

    public function getByID($id)
    {
        return $this->getEntityManager()
            ->getRepository(Transaction::class)
            ->findOneBy([ 'id' => $id ], [ 'createdAt' => 'DESC' ]);
    }

    /**
     * Get a Transaction by orderNumber
     *
     * @param  string $orderNumber
     * @return Transaction
     */
    public function getByOrderNumber($orderNumber)
    {
        return $this->getEntityManager()
            ->getRepository(Transaction::class)
            ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);
    }

    /**
     * Save a Transaction

     * @param  Transaction $transaction
     * @return Transaction
     */
    public function save(Transaction $transaction)
    {
        $now = new DateTime;

        $transaction->setUpdatedAt($now);
        
        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush();

        return $transaction;
    }
}
