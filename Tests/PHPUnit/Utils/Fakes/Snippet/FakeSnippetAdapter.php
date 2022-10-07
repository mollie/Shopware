<?php

namespace MollieShopware\Tests\Utils\Fakes\Snippet;

use MollieShopware\Components\Snippets\SnippetAdapterInterface;

class FakeSnippetAdapter implements SnippetAdapterInterface
{

    /**
     * @param $name
     * @param $default
     * @param $save
     * @return string
     */
    public function get($name, $default = null, $save = false)
    {
        return '';
    }
}
