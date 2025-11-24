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
            'amount_due' => (float) $this->amount_due,
            'tax_amount' => (float) $this->tax_amount,
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
                    'delivery' => $this->when($this->order->relationLoaded('delivery') && $this->order->delivery, function () {
                        return [
                            'id' => $this->order->delivery->id,
                            'delivery_address' => $this->order->delivery->delivery_address,
                            'delivery_status' => $this->order->delivery->delivery_status,
                            'payment_method' => $this->order->delivery->payment_method,
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

