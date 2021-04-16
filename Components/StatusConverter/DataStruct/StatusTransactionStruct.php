<?php

namespace MollieShopware\Components\StatusConverter\DataStruct;


class StatusTransactionStruct
{
    /**
     * @var int|null
     */
    private $targetStatus;

    /**
     * @var bool
     */
    private $ignoreState;

    public function __construct($targetStatus, $ignoreState)
    {
        $this->targetStatus = $targetStatus;
        $this->ignoreState = $ignoreState;
    }

    public function getTargetStatus()
    {
        return $this->targetStatus;
    }

    public function setTargetStatus($targetStatus)
    {
        $this->targetStatus = $targetStatus;
    }

    public function isIgnoreState()
    {
        return $this->ignoreState;
    }

    public function setIgnoreState($ignoreState)
    {
        $this->ignoreState = $ignoreState;
    }
}
