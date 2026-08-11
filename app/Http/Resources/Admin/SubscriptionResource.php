<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class SubscriptionResource extends JsonResource
{
    use ManagesFileUploads;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'offer' => $this->whenLoaded('offer', function () use ($request) {
                $offer = $this->offer;
                $lang = $this->getLanguageFromHeader($request);
                
                // Calculate total price of all condition products
                $conditionProductsTotal = 0.00;
                foreach ($offer->conditions as $condition) {
                    $variant = $condition->productVariant;
                    $product = $condition->product;
                    
                    if ($variant) {
                        $unitPrice = (float) $variant->price;
                    } elseif ($product) {
                        $unitPrice = (float) $product->price;
                    } else {
                        $unitPrice = 0.00;
                    }
                    
                    $quantity = (int) $condition->quantity;
                    $conditionProductsTotal += $unitPrice * $quantity;
                }
                
                // Calculate total price of all reward products
                $rewardProductsTotal = 0.00;
                foreach ($offer->rewards as $reward) {
                    $variant = $reward->productVariant;
                    $product = $reward->product;
                    
                    if ($variant) {
                        $unitPrice = (float) $variant->price;
                    } elseif ($product) {
                        $unitPrice = (float) $product->price;
                    } else {
                        $unitPrice = 0.00;
                    }
                    
                    $quantity = (int) $reward->quantity;
                    $rewardProductsTotal += $unitPrice * $quantity;
                }
                
                // Calculate price_before_discount and price_after_discount based on reward_type
                if ($offer->reward_type === 'products') {
                    $priceBeforeDiscount = $conditionProductsTotal + $rewardProductsTotal;
                    $priceAfterDiscount = $conditionProductsTotal;
                } elseif ($offer->reward_type === 'discount') {
                    $priceBeforeDiscount = $conditionProductsTotal;
                    $totalDiscount = 0.00;
                    
                    foreach ($offer->rewards as $reward) {
                        if ($reward->discount_amount && $reward->discount_type) {
                            if ($reward->discount_type === 'percentage') {
                                $discount = ($priceBeforeDiscount * (float) $reward->discount_amount) / 100;
                                $totalDiscount += $discount;
                            } else {
                                $totalDiscount += (float) $reward->discount_amount;
                            }
                        }
                    }
                    
                    $totalDiscount = min($totalDiscount, $priceBeforeDiscount);
                    $priceAfterDiscount = max(0.00, $priceBeforeDiscount - $totalDiscount);
                } else {
                    $priceBeforeDiscount = $conditionProductsTotal;
                    $priceAfterDiscount = $conditionProductsTotal;
                }
                
                return [
                    'id' => $offer->id,
                    'title_en' => $offer->title_en,
                    'title_ar' => $offer->title_ar,
                    'description_en' => $offer->description_en,
                    'description_ar' => $offer->description_ar,
                    'price_before_discount' => round($priceBeforeDiscount, 3),
                    'price_after_discount' => round($priceAfterDiscount, 3),
                    'conditions' => $offer->conditions->map(function ($condition) {
                        $product = $condition->product;
                        $variant = $condition->productVariant;
                        
                        $originalPrice = $variant ? (float) $variant->price : ($product ? (float) $product->price : null);
                        
                        return [
                            'id' => $condition->id,
                            'product_id' => $product->id,
                            'product_name_ar' => $product->name_ar,
                            'product_name_en' => $product->name_en,
                            'product_sku' => $product->sku,
                            'variant_id' => $variant ? $variant->id : null,
                            'variant_size' => $variant ? $variant->size : null,
                            'variant_short_item' => $variant ? $variant->short_item : null,
                            'variant_sku' => $variant ? $variant->sku : null,
                            'price' => $originalPrice,
                            'available_quantity' => $variant ? $variant->quantity : null,
                            'image' => $variant && $variant->image ? url($variant->image) : null,
                            'variant_is_active' => $variant ? (bool) $variant->is_active : null,
                            'required_quantity' => $condition->quantity,
                            'is_active' => (bool) $condition->is_active,
                        ];
                    }),
                    'rewards' => $offer->rewards->map(function ($reward) {
                        $product = $reward->product;
                        $variant = $reward->productVariant;
                        
                        $originalPrice = $variant ? (float) $variant->price : ($product ? (float) $product->price : null);
                        
                        return [
                            'id' => $reward->id,
                            'product_id' => $product ? $product->id : null,
                            'product_name_ar' => $product ? $product->name_ar : null,
                            'product_name_en' => $product ? $product->name_en : null,
                            'product_sku' => $product ? $product->sku : null,
                            'variant_id' => $variant ? $variant->id : null,
                            'variant_size' => $variant ? $variant->size : null,
                            'variant_short_item' => $variant ? $variant->short_item : null,
                            'variant_sku' => $variant ? $variant->sku : null,
                            'price' => $originalPrice,
                            'available_quantity' => $variant ? $variant->quantity : null,
                            'image' => $variant && $variant->image ? url($variant->image) : null,
                            'variant_is_active' => $variant ? (bool) $variant->is_active : null,
                            'reward_quantity' => $reward->quantity,
                            'discount_amount' => $reward->discount_amount ? (float) $reward->discount_amount : null,
                            'discount_type' => $reward->discount_type,
                            'is_active' => (bool) $reward->is_active,
                        ];
                    }),
                    'image' => $this->getFileUrl($offer->image, 'public', 'no-image.png'),
                    'type' => $offer->type,
                    'offer_points' => (int) $offer->points,
                    'charity_id' => $offer->charity_id,
                    'charity' => $offer->charity ? [
                        'id' => $offer->charity->id,
                        'name_ar' => $offer->charity->name_ar,
                        'name_en' => $offer->charity->name_en,
                        'description' => $lang === 'ar' ? $offer->charity->description_ar : $offer->charity->description_en,
                    ] : null,
                    'reward_type' => $offer->reward_type,
                    'is_subscription' => (bool) $offer->is_subscription,
                    'is_active' => (bool) $offer->is_active,
                    'status' => $this->getOfferStatus($offer),
                    'offer_start_date' => \format_date_app_tz($offer->offer_start_date),
                    'offer_end_date' => \format_date_app_tz($offer->offer_end_date),
                ];
            }),
            'period' => $this->period,
            'period_in_months' => (int) $this->period,
            'points' => (int) $this->points,
            'is_active' => (bool) $this->is_active,
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
        $acceptLanguage = $request->header('Accept-Language');
        
        if ($acceptLanguage) {
            $languages = explode(',', $acceptLanguage);
            $primaryLanguage = trim(explode(';', $languages[0])[0]);
            
            if (in_array($primaryLanguage, ['ar', 'ar-SA', 'ar-EG', 'ar-AE', 'ar-KW', 'ar-BH', 'ar-QA', 'ar-OM', 'ar-YE', 'ar-JO', 'ar-LB', 'ar-SY', 'ar-IQ', 'ar-PS'])) {
                return 'ar';
            }
        }
        
        $customLanguage = $request->header('X-Language');
        if ($customLanguage && in_array($customLanguage, ['en', 'ar'])) {
            return $customLanguage;
        }
        
        return 'en';
    }
}
