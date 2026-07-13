<?php

namespace App\Support;

use App\Models\Customer;

class KuwaitPhone
{
    public const COUNTRY_CODE = '965';

    /**
     * Return local Kuwait phone digits for ERP CustomerCode (no country code 965).
     */
    public static function withoutCountryCode(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        while (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        while (str_starts_with($digits, self::COUNTRY_CODE)) {
            $digits = substr($digits, strlen(self::COUNTRY_CODE));
        }

        return $digits;
    }

    /**
     * Store format used by the admin dashboard: +965 76858548
     */
    public static function normalize(?string $phone, string $defaultCountryCode = self::COUNTRY_CODE): string
    {
        $local = self::withoutCountryCode($phone);
        if ($local === '') {
            return '';
        }

        $country = preg_replace('/\D+/', '', $defaultCountryCode) ?: self::COUNTRY_CODE;

        return '+' . $country . ' ' . $local;
    }

    /**
     * Find a customer by phone regardless of stored format
     * (+965 76858548, 96576858548, +96576858548, etc.).
     */
    public static function findCustomer(?string $phone): ?Customer
    {
        $local = self::withoutCountryCode($phone);
        if ($local === '') {
            return null;
        }

        $normalized = self::normalize($local);
        $digitsWithCountry = self::COUNTRY_CODE . $local;

        return Customer::query()
            ->where(function ($q) use ($local, $normalized, $digitsWithCountry) {
                $q->where('phone', $normalized)
                    ->orWhere('phone', $digitsWithCountry)
                    ->orWhere('phone', '+' . $digitsWithCountry)
                    ->orWhere('phone', $local)
                    ->orWhere('phone', '00' . $digitsWithCountry);
            })
            ->first();
    }

    /**
     * Ensure an existing customer phone is stored in dashboard format.
     */
    public static function ensureStoredFormat(Customer $customer): Customer
    {
        $normalized = self::normalize($customer->phone);
        if ($normalized !== '' && $customer->phone !== $normalized) {
            $customer->update(['phone' => $normalized]);
            $customer->refresh();
        }

        return $customer;
    }
}
