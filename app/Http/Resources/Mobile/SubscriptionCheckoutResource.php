<?php

namespace App\Http\Resources\Mobile;

use App\Models\Subscription;
use App\Support\SubscriptionPricing;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionCheckoutResource extends JsonResource
{
    use ManagesFileUploads;

    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);
        $draft = $this->draft();
        $invoiceAmounts = $draft['invoice_amounts'] ?? [];
        $pricing = SubscriptionPricing::forCheckout($this->resource);
        $subscription = Subscription::query()
            ->with('offer')
            ->find($draft['subscription_id'] ?? null);

        return [
            'id' => $this->id,
            'checkout_number' => $this->checkout_number,
            'status' => $this->status,
            'is_checkout' => true,
            'src' => $this->payment_gateway_src,
            'payment_method' => $pricing['payment_method'],
            'source' => $this->source ?: ($draft['source'] ?? 'app'),
            'total_before_price' => $pricing['total_before_price'],
            'total_after_price' => $pricing['total_after_price'],
            'amount_due' => (float) $this->amount_due,
            'payment_link' => $this->payment_link,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'subscription_id' => $draft['subscription_id'] ?? null,
            'subscription' => $subscription ? [
                'period' => $subscription->period,
                'period_months' => (int) $subscription->period,
                'points' => (int) $subscription->points,
                'offer' => $subscription->offer ? [
                    'id' => $subscription->offer->id,
                    'title' => $lang === 'ar' ? $subscription->offer->title_ar : $subscription->offer->title_en,
                    'image' => $this->getFileUrl($subscription->offer->image, 'public', 'no-image.png'),
                ] : null,
            ] : null,
            'orders_per_month' => (int) ($draft['orders_per_month'] ?? 0),
            'start_date' => $draft['start_date'] ?? null,
            'end_date' => $draft['end_date'] ?? null,
            'total_orders' => (int) ($draft['total_orders'] ?? 0),
            'delivery_schedule' => $draft['monthly_delivery_template'] ?? [],
            'invoice' => [
                'id' => null,
                'invoice_number' => null,
                'amount_due' => (float) $this->amount_due,
                'tax_amount' => (float) ($invoiceAmounts['taxAmount'] ?? 0),
                'delivery_fee' => (float) ($invoiceAmounts['deliveryFee'] ?? 0),
                'status' => 'pending',
                'payment_link' => $this->payment_link,
                'paid_at' => null,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getLanguage(Request $request): string
    {
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));

        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }
}
