<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;

abstract class MobileFormRequest extends FormRequest
{
    /**
     * Get locale from request: LANG header, then Accept-Language, then input('locale'). Default 'ar'.
     */
    protected function getRequestLocale(): string
    {
        $locale = strtolower((string) (
            $this->header('LANG')
            ?? $this->header('Accept-Language')
            ?? $this->input('locale', 'ar')
        ));
        $locale = trim(explode(',', $locale)[0]);
        $locale = trim(explode(';', $locale)[0]);

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }

    /**
     * Set app locale from request so validation messages use the correct language.
     */
    protected function prepareForValidation(): void
    {
        App::setLocale($this->getRequestLocale());
    }

    /**
     * Return message in request locale (en or ar).
     */
    protected function msg(string $en, string $ar): string
    {
        return $this->getRequestLocale() === 'ar' ? $ar : $en;
    }

    /**
     * Handle a failed validation attempt (JSON response for API).
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $this->msg('Validation failed.', 'فشل التحقق من صحة البيانات.'),
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
