<?php

namespace MollieShopware\Components\SessionSnapshot\Services\SessionHashGenerator;

use MollieShopware\Components\SessionSnapshot\SessionHashGeneratorInterface;


class SessionHashGenerator implements SessionHashGeneratorInterface
{

    /**
     * @return string
     */
    public function generateHash()
    {
        return md5(rand(0, 100000) + strtotime(date('curr_date'))) . "_" . time();
    }

}
