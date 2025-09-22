<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $lang === 'ar' ? $this->name_ar : $this->name_en,
            'category' => $this->whenLoaded('category', function () use ($lang) {
                return [
                    'id' => $this->category->id,
                    'name' => $lang === 'ar' ? $this->category->name_ar : $this->category->name_en,
                ];
            }),
            'subcategory' => $this->whenLoaded('subcategory', function () use ($lang) {
                return [
                    'id' => $this->subcategory->id,
                    'name' => $lang === 'ar' ? $this->subcategory->name_ar : $this->subcategory->name_en,
                ];
            }),
            'description' => $lang === 'ar' ? $this->description_ar : $this->description_en,
            'sku' => $this->sku,
            'is_active' => (bool) $this->is_active,
            'variants' => $this->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'size' => $variant->size,
                    'sku' => $variant->sku,
                    'short_item' => $variant->short_item,
                    'quantity' => $variant->quantity,
                    'price' => (float) $variant->price,
                    'image' => $variant->image ? url($variant->image) : null,
                    'is_active' => (bool) $variant->is_active,
                    'created_at' => $variant->created_at?->toISOString(),
                    'updated_at' => $variant->updated_at?->toISOString(),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
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
