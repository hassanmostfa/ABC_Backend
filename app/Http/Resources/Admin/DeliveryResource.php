<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
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
            'payment_method' => $this->payment_method,
            'delivery_address' => $this->delivery_address,
            'block' => $this->block,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'delivery_datetime' => $this->delivery_datetime?->toISOString(),
            'received_datetime' => $this->received_datetime?->toISOString(),
            'delivery_status' => $this->delivery_status,
            'notes' => $this->notes,
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
                    'invoice' => $this->when($this->order->relationLoaded('invoice') && $this->order->invoice, function () {
                        return [
                            'id' => $this->order->invoice->id,
                            'invoice_number' => $this->order->invoice->invoice_number,
                            'amount_due' => (float) $this->order->invoice->amount_due,
                            'status' => $this->order->invoice->status,
                        ];
                    }),
                    'created_at' => $this->order->created_at?->toISOString(),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

