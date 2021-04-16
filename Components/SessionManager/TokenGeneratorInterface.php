<?php

namespace MollieShopware\Components\SessionManager;

interface TokenGeneratorInterface
{

    /**
     * @return string
     */
    public function generateToken();

}
