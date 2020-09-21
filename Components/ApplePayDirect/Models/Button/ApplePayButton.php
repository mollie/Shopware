<?php

namespace MollieShopware\Components\ApplePayDirect\Models\Button;


/**
 * @copyright 2020 dasistweb GmbH (https://www.dasistweb.de)
 */
class ApplePayButton
{

    /**
     * @var bool
     */
    private $active;

    /**
     * @var string
     */
    private $country;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var string
     */
    private $addNumber;


    /**
     * @param bool $active
     * @param string $country
     * @param string $currency
     */
    public function __construct($active, $country, $currency)
    {
        $this->active = $active;
        $this->country = $country;
        $this->currency = $currency;
    }

    /**
     * @return bool
     */
    public function isItemMode()
    {
        return (!empty($this->addNumber));
    }

    /**
     * @param $productNumber
     */
    public function setItemMode($productNumber)
    {
        $this->addNumber = $productNumber;
    }

    /**
     * @return bool[]
     */
    public function toArray()
    {
        $data = array(
            'active' => $this->active,
            'country' => $this->country,
            'currency' => $this->currency,
            'itemMode' => $this->isItemMode(),
        );

        if ($this->isItemMode()) {
            $data['addNumber'] = $this->addNumber;
        }

        return $data;
    }

}
