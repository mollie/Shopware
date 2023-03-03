<?php

namespace MollieShopware\Services\VersionCompare;

class VersionCompare
{

    /**
     * @var string
     */
    private $swVersion;


    /**
     * @param string $swVersion
     */
    public function __construct($swVersion)
    {
        $this->swVersion = $swVersion;
    }


    /**
     * @param string $versionB
     * @return bool
     */
    public function gte($versionB)
    {
        return version_compare($this->swVersion, $versionB, '>=');
    }

    /**
     * @param string $versionB
     * @return bool
     */
    public function gt($versionB)
    {
        return version_compare($this->swVersion, $versionB, '>');
    }

    /**
     * @param string $version
     * @return bool
     */
    public function lt($version)
    {
        return version_compare($this->swVersion, $version, '<');
    }
}
