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
            'customer_id' => $this->customer_id,
            'reference' => $this->reference,
            'type' => $this->type ?? 'order',
            'payment_number' => $this->payment_number,
            'amount' => (float) $this->amount,
            'bonus_amount' => (float) ($this->bonus_amount ?? 0),
            'total_amount' => isset($this->total_amount) ? (float) $this->total_amount : null,
            'method' => $this->method,
            'status' => $this->status,
            'paid_at' => \format_datetime_app_tz($this->paid_at),
            'receipt_id' => $this->receipt_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ] : null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
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
                ] : null),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}

