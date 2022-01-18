<?php

namespace MollieShopware\Components\ApplePayDirect\Models\Button;

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
     * @var DisplayOption[]
     */
    private $displayOption;

    /**
     * @var int[]
     */
    private $restrictionIds;

    /**
     * @var bool
     */
    private $requirePhoneNumber;


    /**
     * @param bool $active
     * @param string $country
     * @param string $currency
     * @param bool $requirePhoneNumber
     */
    public function __construct($active, $country, $currency, $requirePhoneNumber)
    {
        $this->active = $active;
        $this->country = $country;
        $this->currency = $currency;
        $this->requirePhoneNumber = $requirePhoneNumber;

        $this->displayOption = [];
        $this->restrictionIds = [];
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
     * Adds a new display option to the apple pay button.
     * If a restriction exists, it might not be visible in that place.
     *
     * @param DisplayOption $option
     * @param bool $isRestricted
     */
    public function addDisplayOption(DisplayOption $option, $isRestricted)
    {
        if ($isRestricted) {
            $this->restrictionIds[] = $option->getId();
        }

        $this->displayOption[] = $option;
    }

    /**
     * @return bool[]
     */
    public function toArray()
    {
        $data = [
            'active' => $this->active,
            'country' => $this->country,
            'currency' => $this->currency,
            'itemMode' => $this->isItemMode(),
            'requirements' => [
                'phone' => $this->requirePhoneNumber,
            ],
            'displayOptions' => [],
        ];

        if ($this->isItemMode()) {
            $data['addNumber'] = $this->addNumber;
        }

        # add all our restrictions and
        # make sure they are marked as "hidden"
        /** @var DisplayOption $option */
        foreach ($this->displayOption as $option) {
            $isRestricted = in_array($option->getId(), $this->restrictionIds, true);

            $data['displayOptions'][$option->getSmartyKey()] = [
                'visible' => !$isRestricted,
            ];
        }

        return $data;
    }
}
