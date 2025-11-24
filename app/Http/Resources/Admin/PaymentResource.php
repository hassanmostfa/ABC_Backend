<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'payment_number' => $this->payment_number,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'amount_due' => (float) $this->invoice->amount_due,
                    'status' => $this->invoice->status,
                    'order' => $this->when($this->invoice->relationLoaded('order') && $this->invoice->order, function () {
                        return [
                            'id' => $this->invoice->order->id,
                            'order_number' => $this->invoice->order->order_number,
                            'status' => $this->invoice->order->status,
                            'total_amount' => (float) $this->invoice->order->total_amount,
                            'delivery_type' => $this->invoice->order->delivery_type,
                            'customer' => $this->when($this->invoice->order->relationLoaded('customer') && $this->invoice->order->customer, function () {
                                return [
                                    'id' => $this->invoice->order->customer->id,
                                    'name' => $this->invoice->order->customer->name,
                                    'phone' => $this->invoice->order->customer->phone,
                                    'email' => $this->invoice->order->customer->email,
                                ];
                            }),
                            'charity' => $this->when($this->invoice->order->relationLoaded('charity') && $this->invoice->order->charity, function () {
                                return [
                                    'id' => $this->invoice->order->charity->id,
                                    'name_ar' => $this->invoice->order->charity->name_ar,
                                    'name_en' => $this->invoice->order->charity->name_en,
                                    'phone' => $this->invoice->order->charity->phone,
                                ];
                            }),
                            'items' => $this->when($this->invoice->order->relationLoaded('items'), function () {
                                return $this->invoice->order->items->map(function ($item) {
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
                            'delivery' => $this->when($this->invoice->order->relationLoaded('delivery') && $this->invoice->order->delivery, function () {
                                return [
                                    'id' => $this->invoice->order->delivery->id,
                                    'delivery_address' => $this->invoice->order->delivery->delivery_address,
                                    'delivery_status' => $this->invoice->order->delivery->delivery_status,
                                    'payment_method' => $this->invoice->order->delivery->payment_method,
                                ];
                            }),
                        ];
                    }),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

