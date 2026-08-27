<?php

namespace App\Helpers;

class ValidationUtils
{
    /**
     * verify if given password has the required strength
     * @param {string} $password
     * @return {bool} true if password has the required strength
     */
    public static function verifyPasswordStrength($password)
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        return $uppercase && $lowercase && $number && $specialChars;
    }
}
