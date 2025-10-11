<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class OfferResource extends JsonResource
{
    use ManagesFileUploads;
    
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
            'conditions' => $this->conditions->map(function ($condition) use ($lang) {
                $product = $condition->product;
                $variant = $condition->productVariant;
                
                return [
                    'id' => $condition->id,
                    'product_id' => $product->id,
                    // 'product_name' => $lang === 'ar' ? $product->name_ar : $product->name_en,
                    'product_name_ar' => $product->name_ar,
                    'product_name_en' => $product->name_en,
                    'product_sku' => $product->sku,
                    'variant_id' => $variant ? $variant->id : null,
                    'variant_size' => $variant ? $variant->size : null,
                    'variant_short_item' => $variant ? $variant->short_item : null,
                    'variant_sku' => $variant ? $variant->sku : null,
                    'price' => $variant ? (float) $variant->price : null,
                    'available_quantity' => $variant ? $variant->quantity : null,
                    'image' => $variant && $variant->image ? url($variant->image) : null,
                    'variant_is_active' => $variant ? (bool) $variant->is_active : null,
                    'required_quantity' => $condition->quantity,
                    'is_active' => (bool) $condition->is_active,
                ];
            }),
            'rewards' => $this->rewards->map(function ($reward) use ($lang) {
                $product = $reward->product;
                $variant = $reward->productVariant;
                
                return [
                    'id' => $reward->id,
                    'product_id' => $product ? $product->id : null,
                    // 'product_name' => $lang === 'ar' ? $product->name_ar : $product->name_en,
                    'product_name_ar' => $product ? $product->name_ar : null,
                    'product_name_en' => $product ? $product->name_en : null,
                    'product_sku' => $product ? $product->sku : null,
                    'variant_id' => $variant ? $variant->id : null,
                    'variant_size' => $variant ? $variant->size : null,
                    'variant_short_item' => $variant ? $variant->short_item : null,
                    'variant_sku' => $variant ? $variant->sku : null,
                    'price' => $variant ? (float) $variant->price : null,
                    'available_quantity' => $variant ? $variant->quantity : null,
                    'image' => $variant && $variant->image ? url($variant->image) : null,
                    'variant_is_active' => $variant ? (bool) $variant->is_active : null,
                    'reward_quantity' => $reward->quantity,
                    'discount_amount' => $reward->discount_amount ? (float) $reward->discount_amount : null,
                    'discount_type' => $reward->discount_type,
                    'is_active' => (bool) $reward->is_active,
                ];
            }),
            'offer_start_date' => $this->offer_start_date?->toISOString(),
            'offer_end_date' => $this->offer_end_date?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'image' => $this->getFileUrl($this->image, 'public', 'no-image.png'),
            'type' => $this->type,
            'points' => (int) $this->points,
            'charity_id' => $this->charity_id,
            'charity' => $this->whenLoaded('charity', function () use ($lang) {
                return [
                    'id' => $this->charity->id,
                    // 'name' => $lang === 'ar' ? $this->charity->name_ar : $this->charity->name_en,
                    'name_ar' => $this->charity->name_ar,
                    'name_en' => $this->charity->name_en,
                    'description' => $lang === 'ar' ? $this->charity->description_ar : $this->charity->description_en,
                ];
            }),
            'reward_type' => $this->reward_type,
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
