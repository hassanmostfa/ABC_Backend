<?php

use Carbon\Carbon;

if (!function_exists('format_datetime_app_tz')) {
    /**
     * Format a datetime for API response in the application timezone (e.g. Asia/Kuwait).
     * Returns ISO 8601 with offset, e.g. 2026-02-10T00:03:31.000000+03:00
     */
    function format_datetime_app_tz($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $dt = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
        $tz = config('app.timezone', 'Asia/Kuwait');

        return $dt->setTimezone($tz)->format('Y-m-d\TH:i:s.vP');
    }
}

if (!function_exists('format_date_app_tz')) {
    /**
     * Format a date (Y-m-d) in the application timezone.
     * Use for date-only fields so the calendar day is correct for the app locale.
     */
    function format_date_app_tz($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $dt = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
        $tz = config('app.timezone', 'Asia/Kuwait');

        return $dt->setTimezone($tz)->format('Y-m-d');
    }
}
