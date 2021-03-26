<?php

namespace MollieShopware\Tests\Services\TokenAnonymizer;

use MollieShopware\Services\TokenAnonymizer\TokenAnonymizer;
use PHPUnit\Framework\TestCase;

class TokenAnonymizerTest extends TestCase
{

    /**
     * This test verifies that we get an empty string
     * if we have an invalid value that is just NULL.
     *
     * @covers \MollieShopware\Services\TokenAnonymizer\TokenAnonymizer
     */
    public function testAnonymizeNull()
    {
        $anonymizer = new TokenAnonymizer('*', 4, 100);
        $anonymized = $anonymizer->anonymize(null);

        $this->assertEquals('', $anonymized);
    }

    /**
     * This test verifies that we get an empty string
     * if we have an empty string..
     *
     * @covers \MollieShopware\Services\TokenAnonymizer\TokenAnonymizer
     */
    public function testAnonymizeEmpty()
    {
        $anonymizer = new TokenAnonymizer('*', 4, 100);
        $anonymized = $anonymizer->anonymize('');

        $this->assertEquals('', $anonymized);
    }

    /**
     * This test verifies that we get an empty string
     * if we have an invalid text with only spaces.
     *
     * @covers \MollieShopware\Services\TokenAnonymizer\TokenAnonymizer
     */
    public function testAnonymizeSpaces()
    {
        $anonymizer = new TokenAnonymizer('*', 4, 100);
        $anonymized = $anonymizer->anonymize('   ');

        $this->assertEquals('', $anonymized);
    }

    /**
     * This test verifies we successfully anonymize the
     * last 4 digits of our provided string value.
     *
     * @covers \MollieShopware\Services\TokenAnonymizer\TokenAnonymizer
     */
    public function testAnonymizeValue()
    {
        $anonymizer = new TokenAnonymizer('*', 4, 100);
        $anonymized = $anonymizer->anonymize('123456789');

        $this->assertEquals('12345****', $anonymized);
    }

    /**
     * This test verifies that if we have less than the
     * number of letters that should be anonymized, then we
     * make sure to see the first letter, and then add wildcards
     * with the number that we have provided.
     *
     * @covers \MollieShopware\Services\TokenAnonymizer\TokenAnonymizer
     */
    public function testAnonymizeValueIsTooShort()
    {
        $anonymizer = new TokenAnonymizer('*', 4, 100);
        $anonymized = $anonymizer->anonymize('123');

        $this->assertEquals('1****', $anonymized);
    }

    /**
     * This test verifies that we correctly trim to the
     * provided max length of the anonmyized string
     *
     * @covers \MollieShopware\Services\TokenAnonymizer\TokenAnonymizer
     */
    public function testMaxLength()
    {
        $anonymizer = new TokenAnonymizer('*', 4, 10);
        $anonymized = $anonymizer->anonymize('01234567898765432');

        $this->assertEquals('012345****', $anonymized);
    }
}
