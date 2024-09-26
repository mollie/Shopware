<?php

namespace MollieShopware\Tests\PHPUnit\Utils\Fakes\View;

use Enlight_View;
use Enlight_View_Default;

class FakeView extends Enlight_View_Default
{
    /**
     * @var array
     */
    private $items = [];

    /**
     * @param $spec
     * @param $value
     * @param $nocache
     * @param $scope
     * @return Enlight_View|FakeView
     */
    public function assign($spec, $value = null, $nocache = null, $scope = null)
    {
        if ($spec === null) {
            return $this;
        }

        $this->items[$spec] = $value;

        return $this;
    }

    /**
     * @param $spec
     * @return array|mixed
     */
    public function getAssign($spec = null)
    {
        if ($spec === null) {
            return $this->items;
        }

        if (isset($this->items[$spec])) {
            return $this->items[$spec];
        }

        return null;
    }
}
