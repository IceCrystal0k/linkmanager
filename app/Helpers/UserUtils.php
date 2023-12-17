<?php
namespace App\Helpers;

use App\Helpers\CacheUtils;

class UserUtils
{
    /**
     * get user settings, first try to get from cache, then from db
     * @param {number} $userId
     * @return {object} user settings { date_format, date_format_php }
     */
    public static function getUserSetting($userId)
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

    public static function updateUserSetting($userId, $data)
    {
        $cacheUtils = new CacheUtils($userId);
        $response = self::getUserSettingsFormatted($data);
        $cacheUtils->updateUserSettingsCache($response);

    }

    private static function getUserSettingsFormatted($data)
    {
        // set default response
        $response = (object) ['date_format' => config('settings.date_format')[1], 'date_format_php' => config('settings.date_format_php')[1],
            'currency' => 'EUR'];
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
