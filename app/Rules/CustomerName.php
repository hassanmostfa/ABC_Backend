<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\App;

class CustomerName implements ValidationRule
{
    /**
     * English (a-z) and Arabic letters only, with spaces between words.
     */
    public const PATTERN = '/^[a-zA-Z\x{0621}-\x{063A}\x{0641}-\x{064A}]+(?:\s+[a-zA-Z\x{0621}-\x{063A}\x{0641}-\x{064A}]+)*$/u';

    public static function normalize(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = self::normalize($value);

        if ($name === '' || !preg_match(self::PATTERN, $name)) {
            $fail(App::getLocale() === 'ar'
                ? 'الاسم يجب أن يحتوي على حروف إنجليزية أو عربية فقط.'
                : 'The :attribute may only contain English or Arabic letters.');
        }
    }
}
