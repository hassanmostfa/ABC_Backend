<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'invoice_number' => $this->invoice_number,
            'total_before_discounts' => (float) $this->order->total_amount,
            'amount_due' => (float) $this->amount_due,
            'tax_amount' => (float) $this->tax_amount,
            'delivery_fee' => (float) ($this->delivery_fee ?? 0),
            'offer_discount' => (float) $this->offer_discount,
            'used_points' => (int) $this->used_points,
            'points_discount' => (float) $this->points_discount,
            'total_discount' => (float) $this->total_discount,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'payment_method' => $this->order->payment_method,
                    'status' => $this->order->status,
                    'total_amount' => (float) $this->order->total_amount,
                    'delivery_type' => $this->order->delivery_type,
                    'customer' => $this->when($this->order->relationLoaded('customer') && $this->order->customer, function () {
                        return [
                            'id' => $this->order->customer->id,
                            'name' => $this->order->customer->name,
                            'phone' => $this->order->customer->phone,
                            'email' => $this->order->customer->email,
                        ];
                    }),
                    'charity' => $this->when($this->order->relationLoaded('charity') && $this->order->charity, function () {
                        return [
                            'id' => $this->order->charity->id,
                            'name_ar' => $this->order->charity->name_ar,
                            'name_en' => $this->order->charity->name_en,
                            'phone' => $this->order->charity->phone,
                        ];
                    }),
                    'items' => $this->when($this->order->relationLoaded('items'), function () {
                        return $this->order->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'variant_id' => $item->variant_id,
                                'name' => $item->name,
                                'sku' => $item->sku,
                                'quantity' => (int) $item->quantity,
                                'unit_price' => (float) $item->unit_price,
                                'total_price' => (float) $item->total_price,
                                'is_offer' => (bool) $item->is_offer,
                            ];
                        });
                    }),
                    'customer_address' => $this->when($this->order->relationLoaded('customerAddress') && $this->order->customerAddress, function () {
                        return [
                            'id' => $this->order->customerAddress->id,
                            'street' => $this->order->customerAddress->street,
                            'house' => $this->order->customerAddress->house,
                            'block' => $this->order->customerAddress->block,
                            'floor' => $this->order->customerAddress->floor,
                            'country' => $this->when($this->order->customerAddress->relationLoaded('country'), function () {
                                return [
                                    'id' => $this->order->customerAddress->country->id,
                                    'name_ar' => $this->order->customerAddress->country->name_ar,
                                    'name_en' => $this->order->customerAddress->country->name_en,
                                ];
                            }),
                            'governorate' => $this->when($this->order->customerAddress->relationLoaded('governorate'), function () {
                                return [
                                    'id' => $this->order->customerAddress->governorate->id,
                                    'name_ar' => $this->order->customerAddress->governorate->name_ar,
                                    'name_en' => $this->order->customerAddress->governorate->name_en,
                                ];
                            }),
                            'area' => $this->when($this->order->customerAddress->relationLoaded('area'), function () {
                                return [
                                    'id' => $this->order->customerAddress->area->id,
                                    'name_ar' => $this->order->customerAddress->area->name_ar,
                                    'name_en' => $this->order->customerAddress->area->name_en,
                                ];
                            }),
                        ];
                    }),
                    'created_at' => $this->order->created_at?->toISOString(),
                ];
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'amount' => (float) $payment->amount,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'paid_at' => $payment->paid_at?->toISOString(),
                        'created_at' => $payment->created_at?->toISOString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

