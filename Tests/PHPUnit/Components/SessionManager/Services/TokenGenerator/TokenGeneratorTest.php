<?php

namespace MollieShopware\Tests\Components\SessionManager\Services\TokenGenerator;

use MollieShopware\Components\SessionManager\Services\TokenGenerator\TokenGenerator;
use PHPUnit\Framework\TestCase;

class TokenGeneratorTest extends TestCase
{

    /**
     * This test verifies that token length.
     * This has a maximum length of 25 in order to still see it fully
     * in the Mollie Dashboard. Otherwise it is displayed with "..." at the end.
     */
    public function testTokenLength()
    {
        $generator = new TokenGenerator();

        $token = $generator->generateToken();

        $this->assertEquals(25, strlen($token));
    }
}
