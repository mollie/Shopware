<?php

namespace MollieShopware\Components\SessionSnapshot;

interface SessionHashGeneratorInterface
{

    /**
     * @return string
     */
    public function generateHash();

}
