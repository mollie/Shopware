<?php

namespace MollieShopware\Components\Snippets;

interface SnippetAdapterInterface
{

    /**
     * @param $name
     * @param $default
     * @param $save
     * @return mixed
     */
    public function get($name, $default = null, $save = false);
}
