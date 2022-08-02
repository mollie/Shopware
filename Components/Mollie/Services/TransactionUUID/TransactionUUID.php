<?php

namespace MollieShopware\Components\Mollie\Services\TransactionUUID;

use MollieShopware\Models\Transaction;

class TransactionUUID
{

    /**
     * @var TimestampGeneratorInterface
     */
    private $timestampGenerator;


    /**
     * @param TimestampGeneratorInterface $timestampGenerator
     */
    public function __construct(TimestampGeneratorInterface $timestampGenerator)
    {
        $this->timestampGenerator = $timestampGenerator;
    }


    /**
     * @param int $transactionId
     * @param string $basketSignature
     * @return string
     */
    public function generate($transactionId, $basketSignature)
    {
        # current unix timestamp
        $timestamp = $this->timestampGenerator->generateTimestamp();

        # last 4 characters of signature
        $signatureEnding = substr($basketSignature, -4);

        return $timestamp . $transactionId . $signatureEnding;
    }
}
