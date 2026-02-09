<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get language from request header
        $lang = $this->getLanguageFromHeader($request);
        
        return [
            'id' => $this->id,
            // 'name' => $lang === 'ar' ? $this->name_ar : $this->name_en,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'phone' => $this->phone,
            'country' => $this->whenLoaded('country', function () {
                return [
                    'id' => $this->country->id,
                    'name_en' => $this->country->name_en,
                    'name_ar' => $this->country->name_ar,
                ];
            }),
            'governorate' => $this->whenLoaded('governorate', function () {
                return [
                    'id' => $this->governorate->id,
                    'name_en' => $this->governorate->name_en,
                    'name_ar' => $this->governorate->name_ar,
                ];
            }),
            'area' => $this->whenLoaded('area', function () {
                return [
                    'id' => $this->area->id,
                    'name_en' => $this->area->name_en,
                    'name_ar' => $this->area->name_ar,
                ];
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }

    /**
     * Get the offer status based on dates and active state
     */
    private function getOfferStatus($offer): string
    {
        if (!$offer->is_active) {
            return 'inactive';
        }

        $now = now();
        
        if ($offer->offer_start_date > $now) {
            return 'upcoming';
        }
        
        if ($offer->offer_end_date < $now) {
            return 'expired';
        }
        
        return 'active';
    }

    /**
     * Get language from request header
     */
    private function getLanguageFromHeader(Request $request): string
    {
        // Check for Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        
        if ($acceptLanguage) {
            // Parse Accept-Language header (e.g., "ar,en;q=0.9,en-US;q=0.8")
            $languages = explode(',', $acceptLanguage);
            $primaryLanguage = trim(explode(';', $languages[0])[0]);
            
            // Check if it's Arabic
            if (in_array($primaryLanguage, ['ar', 'ar-SA', 'ar-EG', 'ar-AE', 'ar-KW', 'ar-BH', 'ar-QA', 'ar-OM', 'ar-YE', 'ar-JO', 'ar-LB', 'ar-SY', 'ar-IQ', 'ar-PS'])) {
                return 'ar';
            }
        }
        
        // Check for custom X-Language header
        $customLanguage = $request->header('X-Language');
        if ($customLanguage && in_array($customLanguage, ['en', 'ar'])) {
            return $customLanguage;
        }
        
        // Default to English
        return 'en';
    }
}
