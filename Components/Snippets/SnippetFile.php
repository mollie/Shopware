<?php

namespace MollieShopware\Components\Snippets;

class SnippetFile
{

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $file;

    /**
     * @param string $namespace
     * @param string $file
     */
    public function __construct($namespace, $file)
    {
        $this->namespace = $namespace;
        $this->file = $file;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }
}
