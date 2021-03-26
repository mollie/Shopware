<?php

namespace MollieShopware\Facades\FinishCheckout\Models;

class CheckoutFinish
{

    /**
     * @var string
     */
    private $ordernumber;

    /**
     * @var string
     */
    private $temporaryId;

    /**
     * CheckoutFinish constructor.
     * @param string $ordernumber
     * @param string $temporaryId
     */
    public function __construct($ordernumber, $temporaryId)
    {
        $this->ordernumber = $ordernumber;
        $this->temporaryId = $temporaryId;
    }

    /**
     * @return string
     */
    public function getOrdernumber()
    {
        return $this->ordernumber;
    }

    /**
     * @return string
     */
    public function getTemporaryId()
    {
        return $this->temporaryId;
    }
}
