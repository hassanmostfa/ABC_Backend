<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class CustomerSubscriptionResource extends JsonResource
{
    use ManagesFileUploads;

    protected bool $includeOrders = false;

    public function withOrders(bool $include = true): self
    {
        $this->includeOrders = $include;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);
        $orders = $this->relationLoaded('orders') ? $this->orders : null;

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
            'completed_orders' => $orders
                ? $orders->where('status', 'delivered')->count()
                : $this->orders()->where('status', 'delivered')->count(),
            'pending_orders' => $orders
                ? $orders->where('status', 'pending')->count()
                : $this->orders()->where('status', 'pending')->count(),
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
            'orders' => $this->when($this->includeOrders && $orders, function () use ($lang, $orders) {
                return $orders->map(function ($order) use ($lang) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_sequence' => (int) $order->order_sequence,
                        'month_number' => (int) $order->month_number,
                        'order_in_month' => (int) $order->order_in_month,
                        'scheduled_delivery_date' => $order->scheduled_delivery_date?->format('Y-m-d'),
                        'status' => $order->status,
                        'total_amount' => (float) $order->total_amount,
                        'notes' => $order->notes,
                        'items' => $order->relationLoaded('items')
                            ? $order->items->map(function ($item) use ($lang) {
                                $product = $item->relationLoaded('product') ? $item->product : null;
                                $variant = $item->relationLoaded('productVariant') ? $item->productVariant : null;

                                return [
                                    'id' => $item->id,
                                    'product_id' => $item->product_id,
                                    'product_variant_id' => $item->product_variant_id,
                                    'quantity' => (int) $item->quantity,
                                    'unit_price' => (float) $item->unit_price,
                                    'total_price' => (float) $item->total_price,
                                    'type' => $item->type,
                                    'product' => $product ? [
                                        'id' => $product->id,
                                        'name' => $lang === 'ar' ? $product->name_ar : $product->name_en,
                                        'name_ar' => $product->name_ar,
                                        'name_en' => $product->name_en,
                                        'sku' => $product->sku,
                                    ] : null,
                                    'product_variant' => $variant ? [
                                        'id' => $variant->id,
                                        'size' => $variant->size,
                                        'sku' => $variant->sku,
                                        'price' => (float) $variant->price,
                                    ] : null,
                                ];
                            })->values()
                            : [],
                    ];
                })->values();
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getNextDelivery($orders = null): ?array
    {
        if ($orders) {
            $nextOrder = $orders
                ->where('status', 'pending')
                ->filter(function ($order) {
                    return $order->scheduled_delivery_date
                        && $order->scheduled_delivery_date->gte(now()->startOfDay());
                })
                ->sortBy('scheduled_delivery_date')
                ->first();
        } else {
            $nextOrder = $this->orders()
                ->where('status', 'pending')
                ->where('scheduled_delivery_date', '>=', now())
                ->orderBy('scheduled_delivery_date')
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
