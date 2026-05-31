<?php

namespace App\Support;

class KuwaitPhone
{
    /**
     * Return local Kuwait phone digits for ERP CustomerCode (no country code 965).
     */
    public static function withoutCountryCode(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        while (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        while (str_starts_with($digits, '965')) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }
}
