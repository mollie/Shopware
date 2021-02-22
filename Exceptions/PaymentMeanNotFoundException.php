<?php declare(strict_types=1);

namespace MollieShopware\Exceptions;


use Exception;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class PaymentMeanNotFoundException extends Exception
{

    /**
     * @param $paymentMethod
     */
    public function __construct($paymentMethod)
    {
        parent::__construct('PaymentMean for ' . $paymentMethod . ' not found!');
    }

}
