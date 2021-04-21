<?php

namespace MollieShopware\Exceptions;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MolliePaymentNotFound extends \Exception
{

    /**
     * @param $orderId
     */
    public function __construct($orderId)
    {
        parent::__construct('Mollie payment for order : ' . $orderId . ' not found!');
    }
}
