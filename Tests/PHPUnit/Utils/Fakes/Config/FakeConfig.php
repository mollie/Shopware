<?php

namespace MollieShopware\Tests\Utils\Fakes\Config;

use MollieShopware\Components\ConfigInterface;


class FakeConfig implements ConfigInterface
{

    /**
     * @var bool
     */
    private $paymentStatusMail;


    /**
     * @param bool $paymentStatusMail
     */
    public function __construct(bool $paymentStatusMail)
    {
        $this->paymentStatusMail = $paymentStatusMail;
    }

    /**
     * @return bool
     */
    public function isPaymentStatusMailEnabled()
    {
        return $this->paymentStatusMail;
    }

}
