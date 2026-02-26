<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class OfferListResource extends JsonResource
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

        // Calculate total price of all condition products
        $conditionProductsTotal = 0.00;
        foreach ($this->conditions as $condition) {
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
        foreach ($this->rewards as $reward) {
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
        if ($this->reward_type === 'products') {
            // For product rewards: before = conditions + rewards, after = conditions only (rewards are free)
            $priceBeforeDiscount = $conditionProductsTotal + $rewardProductsTotal;
            $priceAfterDiscount = $conditionProductsTotal;
        } elseif ($this->reward_type === 'discount') {
            // For discount rewards: before = conditions only, after = conditions - discount
            $priceBeforeDiscount = $conditionProductsTotal;

            // Calculate total discount from all rewards
            $totalDiscount = 0.00;
            foreach ($this->rewards as $reward) {
                if ($reward->discount_amount && $reward->discount_type) {
                    if ($reward->discount_type === 'percentage') {
                        // Percentage discount on total price
                        $discount = ($priceBeforeDiscount * (float) $reward->discount_amount) / 100;
                        $totalDiscount += $discount;
                    } else {
                        // Fixed discount amount
                        $totalDiscount += (float) $reward->discount_amount;
                    }
                }
            }

            // Don't allow discount to exceed total price
            $totalDiscount = min($totalDiscount, $priceBeforeDiscount);
            $priceAfterDiscount = max(0.00, $priceBeforeDiscount - $totalDiscount);
        } else {
            // No reward type or unknown type: before = after = conditions only
            $priceBeforeDiscount = $conditionProductsTotal;
            $priceAfterDiscount = $conditionProductsTotal;
        }

        return [
            'id' => $this->id,
            'title' => $lang === 'ar' ? $this->title_ar : $this->title_en,
            'description' => $lang === 'ar' ? $this->description_ar : $this->description_en,
            'price_before_discount' => round($priceBeforeDiscount, 3),
            'price_after_discount' => round($priceAfterDiscount, 3),
            'image' => $this->getFileUrl($this->image, 'public', 'no-image.png'),
            'type' => $this->type,
            'points' => (int) $this->points,
            'offer_start_date' => \format_date_app_tz($this->offer_start_date),
            'offer_end_date' => \format_date_app_tz($this->offer_end_date),
            'status' => $this->getOfferStatus(),
            'charity' => $this->whenLoaded('charity', function () use ($lang) {
                return [
                    'id' => $this->charity->id,
                    'name' => $lang === 'ar' ? $this->charity->name_ar : $this->charity->name_en,
                ];
            }),
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
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));
        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }
}
