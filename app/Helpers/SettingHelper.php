<?php

use App\Models\Setting;

if (!function_exists('getSetting')) {
    /**
     * Get a setting value by key from the settings table
     * 
     * @param string $key The setting key
     * @param mixed $default Default value if setting not found
     * @return mixed The setting value or default
     */
    function getSetting(string $key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}

