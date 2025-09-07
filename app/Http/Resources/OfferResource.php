<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
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
            'target_product' => $this->whenLoaded('targetProduct', function () use ($lang) {
                return [
                    'id' => $this->targetProduct->id,
                    'name' => $lang === 'ar' ? $this->targetProduct->name_ar : $this->targetProduct->name_en,
                    'name_en' => $this->targetProduct->name_en,
                    'name_ar' => $this->targetProduct->name_ar,
                    'sku' => $this->targetProduct->sku,
                    'price' => (float) $this->targetProduct->price,
                    'image' => $this->targetProduct->image ? url($this->targetProduct->image) : null,
                ];
            }),
            'target_quantity' => $this->target_quantity,
            'gift_product' => $this->whenLoaded('giftProduct', function () use ($lang) {
                return [
                    'id' => $this->giftProduct->id,
                    'name' => $lang === 'ar' ? $this->giftProduct->name_ar : $this->giftProduct->name_en,
                    'name_en' => $this->giftProduct->name_en,
                    'name_ar' => $this->giftProduct->name_ar,
                    'sku' => $this->giftProduct->sku,
                    'price' => (float) $this->giftProduct->price,
                    'image' => $this->giftProduct->image ? url($this->giftProduct->image) : null,
                ];
            }),
            'gift_quantity' => $this->gift_quantity,
            'offer_start_date' => $this->offer_start_date?->toISOString(),
            'offer_end_date' => $this->offer_end_date?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'image' => $this->image ? url($this->image) : null,
            'status' => $this->getOfferStatus(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the offer status based on dates and active state
     */
    private function getOfferStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        $now = now();
        
        if ($this->offer_start_date > $now) {
            return 'upcoming';
        }
        
        if ($this->offer_end_date < $now) {
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
