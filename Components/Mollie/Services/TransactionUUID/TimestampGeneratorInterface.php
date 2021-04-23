<?php

namespace MollieShopware\Components\Mollie\Services\TransactionUUID;


interface TimestampGeneratorInterface
{

    /**
     * @return string
     */
    public function generateTimestamp();

}
