<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->getLocaleFromRequest($request);

        return [
            'id' => $this->id,
            'question' => $locale === 'ar' ? $this->question_ar : $this->question_en,
            'answer' => $locale === 'ar' ? $this->answer_ar : $this->answer_en,
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * Get locale from Accept-Language header.
     */
    private function getLocaleFromRequest(Request $request): string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if ($acceptLanguage) {
            $languages = explode(',', $acceptLanguage);
            $primaryLanguage = strtolower(trim(explode(';', $languages[0])[0]));

            if (str_starts_with($primaryLanguage, 'ar')) {
                return 'ar';
            }
        }

        return 'en';
    }
}
