<?php

namespace MollieShopware\Components\Mollie\Services\TransactionUUID;


class UnixTimestampGenerator implements TimestampGeneratorInterface
{

    /**
     * @return string
     */
    public function generateTimestamp()
    {
        return (string)time();
    }

}
