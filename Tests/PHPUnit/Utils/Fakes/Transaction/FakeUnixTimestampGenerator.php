<?php

namespace MollieShopware\Tests\Utils\Fakes\Transaction;

use MollieShopware\Components\Mollie\Services\TransactionUUID\TimestampGeneratorInterface;

class FakeUnixTimestampGenerator implements TimestampGeneratorInterface
{

    /**
     * @var string
     */
    private $timestamp;


    /**
     * @param string $timestamp
     */
    public function __construct(string $timestamp)
    {
        $this->timestamp = $timestamp;
    }


    /**
     * @return string
     */
    public function generateTimestamp()
    {
        return $this->timestamp;
    }
}
