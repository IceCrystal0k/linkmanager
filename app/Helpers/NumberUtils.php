<?php
namespace App\Helpers;

class NumberUtils
{
    /**
     * validate a number or a string to be positive integer
     * @param {string|number} $val
     * @return {bool} true if value is positive integer, false otherwise
     */
    public static function isPositiveInteger($val)
    {
        $isNumericString = is_string($val) && ctype_digit($val);
        $isPositiveInteger = is_int($val) && $val > 0;
        return $isNumericString || $isPositiveInteger;
    }
}
