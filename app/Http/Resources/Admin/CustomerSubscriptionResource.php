<?php

namespace App\Http\Resources\Admin;

use App\Traits\CustomerUnreadNotificationsCountTrait;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSubscriptionResource extends JsonResource
{
    use CustomerUnreadNotificationsCountTrait;
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
            'customer_id' => $this->customer_id,
            'subscription_id' => $this->subscription_id,
            'orders_per_month' => (int) $this->orders_per_month,
            'start_date' => \format_date_app_tz($this->start_date),
            'end_date' => \format_date_app_tz($this->end_date),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'total_orders' => (int) $this->total_orders,
            'completed_orders' => (int) ($this->completed_orders_count
                ?? ($this->relationLoaded('orders')
                    ? $this->orders->where('status', 'delivered')->count()
                    : $this->orders()->where('status', 'delivered')->count())),
            'pending_orders' => (int) ($this->pending_orders_count
                ?? ($this->relationLoaded('orders')
                    ? $this->orders->where('status', 'pending')->count()
                    : $this->orders()->where('status', 'pending')->count())),
            'cancelled_orders' => (int) ($this->cancelled_orders_count
                ?? ($this->relationLoaded('orders')
                    ? $this->orders->where('status', 'cancelled')->count()
                    : $this->orders()->where('status', 'cancelled')->count())),
            'metadata' => $this->metadata,
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
                    'paid_at' => \format_datetime_app_tz($this->invoice->paid_at),
                ];
            }),
            'payment_link' => $this->when(
                $this->relationLoaded('invoice') && $this->invoice,
                fn () => $this->invoice->payment_link
            ),
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'email' => $this->customer->email,
                    'customer_code' => $this->customer->customer_code,
                    'points' => (int) ($this->customer->points ?? 0),
                    'is_active' => (bool) $this->customer->is_active,
                    'unread_notifications_count' => $this->getUnreadNotificationsCount($this->customer->id),
                ];
            }),
            'subscription' => $this->whenLoaded('subscription', function () {
                $subscription = $this->subscription;
                $offer = $subscription?->offer;

                return [
                    'id' => $subscription->id,
                    'period' => $subscription->period,
                    'period_in_months' => (int) $subscription->period,
                    'points' => (int) $subscription->points,
                    'is_active' => (bool) $subscription->is_active,
                    'offer' => $offer ? [
                        'id' => $offer->id,
                        'title_en' => $offer->title_en,
                        'title_ar' => $offer->title_ar,
                        'image' => $this->getFileUrl($offer->image, 'public', 'no-image.png'),
                        'reward_type' => $offer->reward_type,
                        'is_subscription' => (bool) $offer->is_subscription,
                    ] : null,
                ];
            }),
            'next_delivery' => $this->getNextDelivery(),
            'orders' => $this->whenLoaded('orders', function () {
                return $this->orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_sequence' => (int) $order->order_sequence,
                        'month_number' => (int) $order->month_number,
                        'order_in_month' => (int) $order->order_in_month,
                        'scheduled_delivery_date' => \format_date_app_tz($order->scheduled_delivery_date),
                        'status' => $order->status,
                        'total_amount' => (float) $order->total_amount,
                        'notes' => $order->notes,
                        'sent_to_erp_at' => \format_datetime_app_tz($order->sent_to_erp_at),
                        'items' => $order->relationLoaded('items')
                            ? $order->items->map(function ($item) {
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
                        'created_at' => \format_datetime_app_tz($order->created_at),
                        'updated_at' => \format_datetime_app_tz($order->updated_at),
                    ];
                })->values();
            }),
            'refund_requests' => $this->whenLoaded('refundRequests', function () {
                return $this->refundRequests->map(function ($refund) {
                    return [
                        'id' => $refund->id,
                        'amount' => (float) $refund->amount,
                        'status' => $refund->status,
                        'reason' => $refund->reason,
                        'admin_notes' => $refund->admin_notes,
                        'created_at' => \format_datetime_app_tz($refund->created_at),
                    ];
                })->values();
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }

    private function getNextDelivery(): ?array
    {
        if ($this->relationLoaded('orders')) {
            $nextOrder = $this->orders
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
                ->where('scheduled_delivery_date', '>=', now()->toDateString())
                ->orderBy('scheduled_delivery_date')
                ->first();
        }

        if (!$nextOrder) {
            return null;
        }

        return [
            'id' => $nextOrder->id,
            'order_number' => $nextOrder->order_number,
            'delivery_date' => \format_date_app_tz($nextOrder->scheduled_delivery_date),
            'month_number' => (int) $nextOrder->month_number,
            'order_in_month' => (int) $nextOrder->order_in_month,
        ];
    }
}
