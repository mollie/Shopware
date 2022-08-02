<?php

namespace MollieShopware\Models\Voucher;

class VoucherType
{
    const NONE = '0';
    const ECO = '1';
    const MEAL = '2';
    const GIFT = '3';


    /**
     * @param $value
     * @return string
     */
    public static function getMollieCategory($value)
    {
        switch ($value) {

            case self::ECO:
                return 'eco';

            case self::MEAL:
                return 'meal';

            case self::GIFT:
                return 'gift';

            default:
                return '';
        }
    }

    /**
     * @param $value
     * @return bool
     */
    public static function isValidVoucher($value)
    {
        $validList = [
            self::ECO,
            self::MEAL,
            self::GIFT
        ];

        return in_array($value, $validList);
    }
}
