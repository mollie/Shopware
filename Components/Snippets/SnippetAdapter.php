<?php

namespace MollieShopware\Components\Snippets;

use Enlight_Components_Snippet_Namespace;

class SnippetAdapter implements SnippetAdapterInterface
{

    /**
     * @var Enlight_Components_Snippet_Namespace
     */
    private $snippets;


    /**
     * @param $snippets
     * @param $snippetNamespace
     */
    public function __construct($snippets, $snippetNamespace)
    {
        $this->snippets = $snippets->getNamespace($snippetNamespace);
    }

    /**
     * @param $name
     * @param $default
     * @param $save
     * @return mixed
     */
    public function get($name, $default = null, $save = false)
    {
        return $this->snippets->get($name, $default, $save);
    }
}
