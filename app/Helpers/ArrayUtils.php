<?php

namespace App\Helpers;

use App\Helpers\NumberUtils;

class ArrayUtils
{
    /**
     * validate an arary to have all values positive integer numbers (or strings representing a number)
     * @param {array} $arr
     * @return {bool} true if all values are positive integers
     */
    public static function validatePositiveIntegers($arr)
    {
        if (empty($arr)) {
            return false;
        }
        foreach ($arr as $val) {
            if (!NumberUtils::isPositiveInteger($val)) {
                return false;
            }
        }
        return true;
    }

    /**
     * validate an arary to have all values positive integer numbers (or strings representing a number) and
     * returns an array with all the string values transformed to numbers
     * @param {array} $arr
     * @return {bool|array} the array with string values transformed to positive integers, false otherwise
     */
    public static function transformToPositiveIntegers($arr)
    {
        if (empty($arr)) {
            return false;
        }
        $response = [];
        foreach ($arr as $key => $val) {
            if (!NumberUtils::isPositiveInteger($val)) {
                return false;
            } else {
                $response[$key] = (int)$val;
            }
        }
        return $response;
    }

    /**
     * clone an array of objects
     *
     * @param array $array to clone
     * @return array the cloned array
     */
    public static function array_clone($array)
    {
        return array_map(function ($element) {
            return ((is_array($element))
                ? self::array_clone($element)
                : ((is_object($element))
                    ? clone $element
                    : $element
                )
            );
        }, $array);
    }
}
