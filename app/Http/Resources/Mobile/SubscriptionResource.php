<?php

namespace App\Http\Resources\Mobile;

use App\Support\SubscriptionPricing;
use App\Support\SubscriptionSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    protected bool $includeFullOffer = false;

    public function withFullOffer(bool $full = true): self
    {
        $this->includeFullOffer = $full;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);
        $pricing = SubscriptionPricing::forPlan($this->resource);

        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'offer' => $this->whenLoaded('offer', function () use ($request) {
                if ($this->includeFullOffer) {
                    return (new OfferResource($this->offer))->toArray($request);
                }

                return (new OfferListResource($this->offer))->toArray($request);
            }),
            'period' => $this->period,
            'period_months' => (int) $this->period,
            'period_label' => $this->getPeriodLabel($lang),
            'sizes' => SubscriptionSize::fromOffer($this->offer),
            'points' => (int) $this->points,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value ? (float) $this->discount_value : null,
            'discount_free_months' => $this->discount_free_months ? (int) $this->discount_free_months : null,
            'discount_label' => $this->getDiscountLabel($lang),
            'has_discount' => $this->hasDiscount(),
            'effective_period_for_pricing' => $this->getEffectivePeriodForPricing(),
            'total_before_price' => $pricing['total_before_price'],
            'total_after_price' => $pricing['total_after_price'],
            'payment_method' => $pricing['payment_method'],
            'is_active' => (bool) $this->is_active,
        ];
    }

    private function getPeriodLabel(string $lang): string
    {
        $labels = [
            '3' => ['en' => '3 Months', 'ar' => '3 أشهر'],
            '6' => ['en' => '6 Months', 'ar' => '6 أشهر'],
            '12' => ['en' => '12 Months', 'ar' => '12 شهر'],
        ];

        return $labels[$this->period][$lang] ?? $this->period;
    }

    private function getLanguage(Request $request): string
    {
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));

        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }

    private function getDiscountLabel(string $lang): ?string
    {
        if (!$this->hasDiscount()) {
            return null;
        }

        switch ($this->discount_type) {
            case 'percentage':
                return $lang === 'ar' 
                    ? "خصم {$this->discount_value}%" 
                    : "{$this->discount_value}% Discount";
            
            case 'fixed':
                return $lang === 'ar' 
                    ? "خصم {$this->discount_value} د.ك" 
                    : "Discount {$this->discount_value} KD";
            
            case 'free_months':
                $monthsLabel = $lang === 'ar' 
                    ? ($this->discount_free_months == 1 ? 'شهر' : 'أشهر')
                    : ($this->discount_free_months == 1 ? 'month' : 'months');
                
                return $lang === 'ar' 
                    ? "{$this->discount_free_months} {$monthsLabel} مجاناً" 
                    : "{$this->discount_free_months} {$monthsLabel} free";
            
            default:
                return null;
        }
    }
}
