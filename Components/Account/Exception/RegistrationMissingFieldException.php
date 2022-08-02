<?php

namespace MollieShopware\Components\Account\Exception;

class RegistrationMissingFieldException extends \Exception
{

    /**
     * @var string
     */
    private $field;

    /**
     * @param string $field
     */
    public function __construct($field)
    {
        parent::__construct('Missing Field: ' . $field);

        $this->field = $field;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }
}
