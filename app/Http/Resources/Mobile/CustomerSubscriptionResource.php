<?php

namespace App\Http\Resources\Mobile;

use App\Support\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class CustomerSubscriptionResource extends JsonResource
{
    use ManagesFileUploads;

    protected bool $includeOrders = false;

    protected ?string $orderStatusFilter = null;

    public function withOrders(bool $include = true, ?string $statusFilter = null): self
    {
        $this->includeOrders = $include;
        $this->orderStatusFilter = $statusFilter;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);
        $orders = $this->relationLoaded('orders') ? $this->orders : null;
        $pricing = SubscriptionPricing::forCustomerSubscription($this->resource);

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
            'total_before_price' => $pricing['total_before_price'],
            'total_after_price' => $pricing['total_after_price'],
            'payment_method' => $pricing['payment_method'],
            'source' => $this->source ?: 'app',
            'total_orders' => $this->total_orders,
            'completed_orders' => (int) ($this->completed_orders_count
                ?? ($orders
                    ? $orders->where('status', 'delivered')->count()
                    : $this->orders()->where('status', 'delivered')->count())),
            'pending_orders' => (int) ($this->pending_orders_count
                ?? ($orders
                    ? $orders->where('status', 'pending')->count()
                    : $this->orders()->where('status', 'pending')->count())),
            'next_delivery' => $this->getNextDelivery($orders),
            'invoice' => $this->whenLoaded('invoice', function () {
                if (!$this->invoice) {
                    return null;
                }

                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'amount_due' => (float) $this->invoice->amount_due,
                    'tax_amount' => (float) $this->invoice->tax_amount,
                    'delivery_fee' => (float) ($this->invoice->delivery_fee ?? 0),
                    'status' => $this->invoice->status,
                    'payment_link' => $this->invoice->payment_link,
                    'paid_at' => $this->invoice->paid_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'payment_link' => $this->when(
                $this->relationLoaded('invoice') && $this->invoice,
                fn () => $this->invoice->payment_link
            ),
            'orders' => $this->when($this->includeOrders, function () use ($orders) {
                return SubscriptionOrderResource::collection($orders ?? collect())->resolve();
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getNextDelivery($orders = null): ?array
    {
        if ($this->orderStatusFilter || $orders === null) {
            $nextOrder = $this->orders()
                ->where('status', 'pending')
                ->where('scheduled_delivery_date', '>=', now()->toDateString())
                ->orderBy('scheduled_delivery_date')
                ->first();
        } else {
            $nextOrder = $orders
                ->where('status', 'pending')
                ->filter(function ($order) {
                    return $order->scheduled_delivery_date
                        && $order->scheduled_delivery_date->gte(now()->startOfDay());
                })
                ->sortBy('scheduled_delivery_date')
                ->first();
        }

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
