<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Services;

use MollieShopware\Components\ApplePayDirect\Services\ApplePayFormatter;
use MollieShopware\Tests\Utils\Fakes\Snippet\FakeSnippetAdapter;
use PHPUnit\Framework\TestCase;

class ApplePayFormatterTest extends TestCase
{


    /**
     *
     */
    public function setUp(): void
    {
    }

    /**
     * This test verifies that our shipping method array struct is
     * correctly converted into a valid Apple Pay format.
     * @return void
     */
    public function testFormatShippingMethod()
    {
        $fakeSnippets = new FakeSnippetAdapter();

        $formatter = new ApplePayFormatter($fakeSnippets);

        $method = [
            'id' => 1,
            'name' => 'DHL',
            'description' => 'DHL Shipping',
        ];

        $formatted = $formatter->formatShippingMethod($method, 10);

        $expected = [
            'identifier' => 1,
            'label' => 'DHL',
            'detail' => 'DHL Shipping',
            'amount' => 10,
        ];

        $this->assertEquals($expected, $formatted);
    }

    /**
     * This test verifies that we remove any detail for Apple Pay if
     * we find any of our known HMTL tag.
     * This doesn't look good, so we rather go without a detail description.
     *
     * @testWith        ["<span></span>"]
     *                  ["<img></img>"]
     *                  ["<a></a>"]
     *                  ["<p></p>"]
     *
     * @param string $html
     * @return void
     */
    public function testFormatShippingMethodRemoveHTML_Span(string $html)
    {
        $method = [
            'id' => 1,
            'name' => 'DHL',
            'description' => 'DHL Shipping ' . $html,
        ];


        $fakeSnippets = new FakeSnippetAdapter();
        $formatter = new ApplePayFormatter($fakeSnippets);

        $formatted = $formatter->formatShippingMethod($method, 10);

        $this->assertEquals('', $formatted['detail']);
    }
}
