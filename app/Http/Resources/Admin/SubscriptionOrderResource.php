<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_subscription_id' => $this->customer_subscription_id,
            'customer_id' => $this->customer_id,
            'order_sequence' => (int) $this->order_sequence,
            'month_number' => (int) $this->month_number,
            'order_in_month' => (int) $this->order_in_month,
            'scheduled_delivery_date' => $this->scheduled_delivery_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'sent_to_erp_at' => $this->sent_to_erp_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'customer' => $this->when($this->relationLoaded('customer'), function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'email' => $this->customer->email,
                    'customer_code' => $this->customer->customer_code,
                ];
            }),
            'customer_subscription' => $this->when($this->relationLoaded('customerSubscription'), function () {
                return [
                    'id' => $this->customerSubscription->id,
                    'status' => $this->customerSubscription->status,
                    'start_date' => $this->customerSubscription->start_date?->format('Y-m-d'),
                    'end_date' => $this->customerSubscription->end_date?->format('Y-m-d'),
                    'subscription' => $this->when(
                        $this->customerSubscription->relationLoaded('subscription'),
                        function () {
                            return [
                                'id' => $this->customerSubscription->subscription->id,
                                'period' => $this->customerSubscription->subscription->period,
                                'points' => $this->customerSubscription->subscription->points,
                                'offer' => $this->when(
                                    $this->customerSubscription->subscription->relationLoaded('offer'),
                                    function () {
                                        return [
                                            'id' => $this->customerSubscription->subscription->offer->id,
                                            'title_en' => $this->customerSubscription->subscription->offer->title_en,
                                            'title_ar' => $this->customerSubscription->subscription->offer->title_ar,
                                        ];
                                    }
                                ),
                            ];
                        }
                    ),
                ];
            }),
            'items' => $this->when($this->relationLoaded('items'), function () {
                return $this->items->map(function ($item) {
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
                            'name_en' => $product->name_en,
                            'name_ar' => $product->name_ar,
                            'sku' => $product->sku,
                        ] : null,
                        'product_variant' => $variant ? [
                            'id' => $variant->id,
                            'size' => $variant->size,
                            'sku' => $variant->sku,
                            'price' => (float) $variant->price,
                        ] : null,
                    ];
                })->values();
            }),
        ];
    }
}
