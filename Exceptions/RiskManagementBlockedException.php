<?php

namespace MollieShopware\Exceptions;

class RiskManagementBlockedException extends \Exception
{

    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }

}
