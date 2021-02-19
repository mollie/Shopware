<?php

declare(strict_types=1);


namespace MollieShopware\Components\StatusMapping\DataStruct;


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

    public function setTargetStatus($targetStatus): void
    {
        $this->targetStatus = $targetStatus;
    }

    public function isIgnoreState()
    {
        return $this->ignoreState;
    }

    public function setIgnoreState($ignoreState): void
    {
        $this->ignoreState = $ignoreState;
    }
}