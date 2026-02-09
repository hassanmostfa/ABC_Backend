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
            'paid_at' => \format_datetime_app_tz($this->paid_at),
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
                        $addr = $this->order->customerAddress;
                        return [
                            'id' => $addr->id,
                            'type' => $addr->type ?? 'house',
                            'formatted_address' => $addr->formatted_address ?? null,
                            'phone_number' => $addr->phone_number,
                            'street' => $addr->street,
                            'house' => $addr->house,
                            'block' => $addr->block,
                            'floor' => $addr->floor,
                            'building_name' => $addr->building_name,
                            'apartment_number' => $addr->apartment_number,
                            'company' => $addr->company,
                            'country' => $this->when($addr->relationLoaded('country') && $addr->country, fn () => [
                                'id' => $addr->country->id,
                                'name_ar' => $addr->country->name_ar,
                                'name_en' => $addr->country->name_en,
                            ]),
                            'governorate' => $this->when($addr->relationLoaded('governorate') && $addr->governorate, fn () => [
                                'id' => $addr->governorate->id,
                                'name_ar' => $addr->governorate->name_ar,
                                'name_en' => $addr->governorate->name_en,
                            ]),
                            'area' => $this->when($addr->relationLoaded('area') && $addr->area, fn () => [
                                'id' => $addr->area->id,
                                'name_ar' => $addr->area->name_ar,
                                'name_en' => $addr->area->name_en,
                            ]),
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
                        'paid_at' => \format_datetime_app_tz($payment->paid_at),
                        'created_at' => \format_datetime_app_tz($payment->created_at),
                    ];
                });
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}

