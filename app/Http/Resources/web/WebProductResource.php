<?php

namespace App\Http\Resources\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * This resource returns variants as separate products for web consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get language from request header
        $lang = $this->getLanguageFromHeader($request);
        
        // If the resource is a ProductVariant, transform it as a separate product
        if ($this->resource instanceof \App\Models\ProductVariant) {
            return $this->transformVariantAsProduct($lang);
        }
        
        // If the resource is a Product, transform each variant as a separate product
        if ($this->resource instanceof \App\Models\Product) {
            return $this->transformProductVariants($lang);
        }
        
        // If the resource is a flattened object (from flattenVariantsAsProducts), transform it directly
        if (is_object($this->resource) && property_exists($this->resource, 'variant_id')) {
            return $this->transformFlattenedVariant($lang);
        }
        
        return [];
    }
    
    /**
     * Transform a single variant as a separate product
     */
    private function transformVariantAsProduct(string $lang): array
    {
        $variant = $this->resource;
        $product = $variant->product;
        
        return [
            'id' => $variant->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'name' => $lang === 'ar' ? $product->name_ar : $product->name_en,
            'description' => $lang === 'ar' ? $product->description_ar : $product->description_en,
            'product_sku' => $product->sku,
            'variant_sku' => $variant->sku,
            'size' => $variant->size,
            'short_item' => $variant->short_item,
            'quantity' => $variant->quantity,
            'price' => (float) $variant->price,
            'image' => $variant->image ? url($variant->image) : null,
            'is_active' => (bool) $variant->is_active,
            'category' => $this->whenLoaded('product.category', function () use ($lang) {
                return [
                    'id' => $this->product->category->id,
                    'name' => $lang === 'ar' ? $this->product->category->name_ar : $this->product->category->name_en,
                ];
            }),
            'subcategory' => $this->whenLoaded('product.subcategory', function () use ($lang) {
                return [
                    'id' => $this->product->subcategory->id,
                    'name' => $lang === 'ar' ? $this->product->subcategory->name_ar : $this->product->subcategory->name_en,
                ];
            }),
            'created_at' => $variant->created_at?->toISOString(),
            'updated_at' => $variant->updated_at?->toISOString(),
        ];
    }
    
    /**
     * Transform product variants as separate products
     */
    private function transformProductVariants(string $lang): array
    {
        $product = $this->resource;
        $variants = $product->variants ?? collect();
        
        // Transform each variant as a separate product
        return $variants->map(function ($variant) use ($lang, $product) {
            return [
                'id' => $variant->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'name' => $lang === 'ar' ? $product->name_ar : $product->name_en,
                'description' => $lang === 'ar' ? $product->description_ar : $product->description_en,
                'product_sku' => $product->sku,
                'variant_sku' => $variant->sku,
                'size' => $variant->size,
                'short_item' => $variant->short_item,
                'quantity' => $variant->quantity,
                'price' => (float) $variant->price,
                'image' => $variant->image ? url($variant->image) : null,
                'is_active' => (bool) $variant->is_active,
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
                'created_at' => $variant->created_at?->toISOString(),
                'updated_at' => $variant->updated_at?->toISOString(),
            ];
        })->toArray();
    }
    
    /**
     * Transform a flattened variant object as a separate product
     */
    private function transformFlattenedVariant(string $lang): array
    {
        $variant = $this->resource;
        
        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->variant_id,
            'name' => $lang === 'ar' ? $variant->name_ar : $variant->name_en,
            'description' => $lang === 'ar' ? $variant->description_ar : $variant->description_en,
            'product_sku' => $variant->product_sku,
            'variant_sku' => $variant->variant_sku,
            'size' => $variant->size,
            'short_item' => $variant->short_item,
            'quantity' => $variant->quantity,
            'price' => (float) $variant->price,
            'image' => $variant->image ? url($variant->image) : null,
            'is_active' => (bool) $variant->is_active,
            'category' => $variant->category ? [
                'id' => $variant->category->id,
                'name' => $lang === 'ar' ? $variant->category->name_ar : $variant->category->name_en,
            ] : null,
            'subcategory' => $variant->subcategory ? [
                'id' => $variant->subcategory->id,
                'name' => $lang === 'ar' ? $variant->subcategory->name_ar : $variant->subcategory->name_en,
            ] : null,
            'created_at' => $variant->created_at?->toISOString(),
            'updated_at' => $variant->updated_at?->toISOString(),
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
