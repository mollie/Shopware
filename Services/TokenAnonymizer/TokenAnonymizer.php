<?php

namespace MollieShopware\Services\TokenAnonymizer;

class TokenAnonymizer
{

    /**
     * @var string
     */
    private $placeholderSymbol;

    /**
     * @var int
     */
    private $placeholderCount;

    /**
     * @var int
     */
    private $maxLength;

    /**
     * @param string $placeholderSymbol
     * @param int $placeholderCount
     * @param int $maxLength
     */
    public function __construct($placeholderSymbol, $placeholderCount, $maxLength)
    {
        $this->placeholderSymbol = $placeholderSymbol;
        $this->placeholderCount = $placeholderCount;
        $this->maxLength = $maxLength;
    }


    /**
     * @param $value
     * @return false|string
     */
    public function anonymize($value)
    {
        if ($value === null) {
            return '';
        }

        if (trim((string)$value) === '') {
            return '';
        }

        if (strlen((string)$value) < $this->placeholderCount) {
            return $value[0] . $this->getPlaceholder();
        }

        # only get the original value up to
        # the allowed max length
        $value = substr($value, 0, $this->maxLength);

        # replace the last 4 characters with our placeholders
        return substr($value, 0, -4) . $this->getPlaceholder();
    }

    /**
     * @return string
     */
    private function getPlaceholder()
    {
        return str_repeat($this->placeholderSymbol, $this->placeholderCount);
    }

}
