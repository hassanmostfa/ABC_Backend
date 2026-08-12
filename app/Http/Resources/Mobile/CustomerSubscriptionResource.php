<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class CustomerSubscriptionResource extends JsonResource
{
    use ManagesFileUploads;

    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);
        
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'subscription' => [
                'period' => $this->subscription->period,
                'period_months' => (int) $this->subscription->period,
                'points' => (int) $this->subscription->points,
                'offer' => [
                    'id' => $this->subscription->offer->id,
                    'title' => $lang === 'ar' ? $this->subscription->offer->title_ar : $this->subscription->offer->title_en,
                    'image' => $this->getFileUrl($this->subscription->offer->image, 'public', 'no-image.png'),
                ],
            ],
            'orders_per_month' => $this->orders_per_month,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'total_orders' => $this->total_orders,
            'completed_orders' => $this->orders()->where('status', 'delivered')->count(),
            'pending_orders' => $this->orders()->where('status', 'pending')->count(),
            'next_delivery' => $this->getNextDelivery(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getNextDelivery(): ?array
    {
        $nextOrder = $this->orders()
            ->where('status', 'pending')
            ->where('scheduled_delivery_date', '>=', now())
            ->orderBy('scheduled_delivery_date')
            ->first();

        if (!$nextOrder) {
            return null;
        }

        return [
            'order_number' => $nextOrder->order_number,
            'delivery_date' => $nextOrder->scheduled_delivery_date->format('Y-m-d'),
            'month_number' => $nextOrder->month_number,
            'order_in_month' => $nextOrder->order_in_month,
        ];
    }

    private function getLanguage(Request $request): string
    {
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));
        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }
}
