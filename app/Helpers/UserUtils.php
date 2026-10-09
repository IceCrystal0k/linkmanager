<?php

namespace App\Helpers;

use App\Helpers\CacheUtils;
use Illuminate\Support\Facades\Hash;
use App\Models\ExchangeToken;

class UserUtils
{
    /**
     * get user settings, first try to get from cache, then from db
     * @param int $userId
     * @return object user settings { date_format, date_format_php }
     */
    public static function getUserSetting(int $userId): object
    {
        $cacheUtils = new CacheUtils($userId);
        $userSettings = $cacheUtils->getUserSettings();
        if ($userSettings && isset($userSettings->date_format)) {
            return $userSettings;
        }

        $userInfoData = \App\Models\UserInfo::where('user_id', $userId)->select(['date_format', 'date_format_separator'])->first();
        $response = self::getUserSettingsFormatted($userInfoData);

        $cacheUtils->updateUserSettingsCache($response);

        return $response;
    }

    /**
     * update user setting
     * @param int $userId
     * @param object $data
     * @return void
     */
    public static function updateUserSetting(int $userId, object $data): void
    {
        $cacheUtils = new CacheUtils($userId);
        $response = self::getUserSettingsFormatted($data);
        $cacheUtils->updateUserSettingsCache($response);
    }

    /**
     * generate token
     * @param int $userId
     * @return string
     */
    public static function generateToken(int $userId): string
    {
        $secret = 'z$cl323M914vnm_EF32!';
        $randPos = rand(1, strlen($secret) - 2);
        $secret = substr($secret, 0, $randPos) . $userId . substr($secret, $randPos);
        $timeSlot = (int) (time() / 30); // 30-second window
        $exchangeCode = hash_hmac('sha256', $timeSlot, $secret);
        return $exchangeCode;
    }

    /**
     * create social auth exchange code
     * @param string $target
     * @param int $userId
     * @return string
     */
    public static function createSocialAuthExchangeCode(string $target, int $userId): string
    {
        $existingItem = ExchangeToken::where('name', $target)
            ->where('user_id', $userId)->first();

        $token = self::generateToken($userId);
        $expiresAt = now()->addSeconds(30);
        if ($existingItem && $existingItem->id) {
            $existingItem->token = $token;
            $existingItem->expires_at = $expiresAt;
            $existingItem->save();
        } else {
            $saveData = ['name' => $target, 'user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt];
            ExchangeToken::create($saveData);
        }
        return $token;
    }

    /**
     * verify social auth exchange code
     * @param string $target
     * @param int $userId
     * @param string $token
     * @return bool
     */
    public static function verifySocialAuthExchangeCode(string $target, int $userId, string $token): bool
    {
        $nowTimestamp = now();
        $data = ExchangeToken::select('id')->where('name', $target)->where('token', $token)
            ->where('user_id', $userId)->where('expires_at', '>', $nowTimestamp)->first();
        return $data ? true : false;
    }

    /**
     * delete social auth exchange code
     * @param string $target
     * @param int $userId
     * @return void
     */
    public static function deleteSocialAuthExchangeCode(string $target, int $userId): void
    {
        ExchangeToken::where('name', $target)->where('user_id', $userId)->delete();
    }

    /**
     * get user settings formatted
     * @param object|null $data
     * @return object
     */
    private static function getUserSettingsFormatted(?object $data): object
    {
        // set default response
        $response = (object) [
            'date_format' => config('settings.date_format')[1],
            'date_format_php' => config('settings.date_format_php')[1],
            'currency' => 'EUR'
        ];
        if ($data) {
            // if ($data->currency) {
            //     $response->currency = $data->currency;
            // }
            if (!$data->date_format) {
                $data->date_format = 1;
            }
            if (!$data->date_format_separator) {
                $data->date_format_separator = 1;
            }

            $dateFormat = config('settings.date_format')[$data->date_format];
            $dateFormatPhp = config('settings.date_format_php')[$data->date_format];
            $dateFormatSeparator = config('settings.date_format_separator')[$data->date_format_separator];

            if ($dateFormatSeparator !== '/') {
                $dateFormat = str_replace('/', $dateFormatSeparator, $dateFormat);
                $dateFormatPhp = str_replace('/', $dateFormatSeparator, $dateFormatPhp);
            }

            $response->date_format = $dateFormat;
            $response->date_format_php = $dateFormatPhp;
        }
        return $response;
    }
}
