<?php

	// Mollie Shopware Plugin Version: 1.3.2

namespace MollieShopware\Components;

class Url
{
    /**
     * @var string
     */
    public $scheme;

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $port;

    /**
     * @var string
     */
    public $path;

    /**
     * @var array
     */
    public $queryVars = [];

    /**
     * @var string
     */
    public $fragment;

    public function __construct($url)
    {
        $this->scheme = parse_url($url, PHP_URL_SCHEME);
        $this->username = parse_url($url, PHP_URL_USER);
        $this->password = parse_url($url, PHP_URL_PASS);
        $this->host = parse_url($url, PHP_URL_HOST);
        $this->port = parse_url($url, PHP_URL_PORT);
        $this->path = parse_url($url, PHP_URL_PATH);
        $this->queryVars = $this->parseQuery(parse_url($url, PHP_URL_QUERY));
        $this->fragment = parse_url($url, PHP_URL_FRAGMENT);
    }

    public function parseQuery($query)
    {
        $queryVars = [];
        parse_str($query, $queryVars);
        return $queryVars;
    }

    public function getQuery()
    {
        $query = http_build_query($this->queryVars);
        return empty($query) ? '' : '?' . $query;
    }

    public function get()
    {
        return $this->scheme .
            (!empty($this->scheme) ? '://' : '//') .
            $this->username .
            (!empty($this->username) && !empty($this->password) ? ':' : '') .
            (!empty($this->username) ? $this->password : '') .
            (!empty($this->username) ? '@' : '') .
            $this->host .
            (!empty($this->port) ? ':' : '') .
            $this->port .
            $this->path .
            $this->getQuery() .
            (!empty($this->fragment) ? '#' : '') .
            $this->fragment;
    }

    public function __toString()
    {
        return $this->get();
    }
}
