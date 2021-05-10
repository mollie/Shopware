<?php

namespace MollieShopware\Components\TransactionBuilder\Models;

class TaxMode
{

    /**
     * @var bool
     */
    private $chargeTaxes;


    /**
     * TaxMode constructor.
     * @param $chargeTaxes
     */
    public function __construct($chargeTaxes)
    {
        $this->chargeTaxes = $chargeTaxes;
    }

    /**
     * @return bool
     */
    public function isChargeTaxes()
    {
        return $this->chargeTaxes;
    }

}
