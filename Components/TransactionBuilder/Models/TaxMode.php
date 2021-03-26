<?php

namespace MollieShopware\Components\TransactionBuilder\Models;

class TaxMode
{

    /**
     * @var bool
     */
    private $chargeTaxes;

    /**
     * @var bool
     */
    private $netOrder;

    /**
     * @param bool $chargeTaxes
     * @param bool $isNetOrder
     */
    public function __construct($chargeTaxes, $isNetOrder)
    {
        $this->chargeTaxes = $chargeTaxes;
        $this->netOrder = $isNetOrder;
    }

    /**
     * @return bool
     */
    public function isChargeTaxes()
    {
        return $this->chargeTaxes;
    }

    /**
     * @return bool
     */
    public function isNetOrder()
    {
        return $this->netOrder;
    }
}
