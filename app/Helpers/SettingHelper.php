<?php

use App\Models\Setting;

if (!function_exists('getSetting')) {

    function getSetting(string $key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}

if (!function_exists('getSettingTranslated')) {

    function getSettingTranslated(string $key, string $locale = 'en', $default = null)
    {
        return Setting::getTranslatedValue($key, $locale, $default);
    }
}

