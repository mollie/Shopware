<?php

namespace MollieShopware\Components\ApplePayDirect\Models\Button;

class DisplayOption
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $smartyKey;

    /**
     * @var string
     */
    private $name;

    /**
     * @param int $id
     * @param string $smartyKey
     * @param string $name
     */
    public function __construct($id, $smartyKey, $name)
    {
        $this->id = $id;
        $this->smartyKey = $smartyKey;
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSmartyKey()
    {
        return $this->smartyKey;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

}
