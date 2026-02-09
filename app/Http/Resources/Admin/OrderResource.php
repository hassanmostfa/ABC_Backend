<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'customer_address' => $this->whenLoaded('customerAddress', function () {
                $addr = $this->customerAddress;
                return [
                    'id' => $addr->id,
                    'type' => $addr->type ?? 'house',
                    'formatted_address' => $addr->formatted_address ?? null,
                    'lat' => $addr->lat ? (float) $addr->lat : null,
                    'lng' => $addr->lng ? (float) $addr->lng : null,
                    'phone_number' => $addr->phone_number,
                    'block' => $addr->block,
                    'street' => $addr->street,
                    'house' => $addr->house,
                    'building_name' => $addr->building_name,
                    'apartment_number' => $addr->apartment_number,
                    'company' => $addr->company,
                    'floor' => $addr->floor,
                    'additional_directions' => $addr->additional_directions,
                    'country' => $addr->country?->name_en,
                    'governorate' => $addr->governorate?->name_en,
                    'area' => $addr->area?->name_en,
                ];
            }),
            'charity_id' => $this->charity_id,
            'type' => $this->charity_id ? 'charity' : ($this->customer_id ? 'customer' : null),
            'payment_method' => $this->payment_method,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'offer_snapshot' => $this->offer_snapshot,
            'delivery_type' => $this->delivery_type,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'email' => $this->customer->email,
                    'points' => (int) ($this->customer->points ?? 0),
                    'is_active' => (bool) $this->customer->is_active,
                ];
            }),
            'charity' => $this->whenLoaded('charity', function () {
                return [
                    'id' => $this->charity->id,
                    'name_ar' => $this->charity->name_ar,
                    'name_en' => $this->charity->name_en,
                    'phone' => $this->charity->phone,
                ];
            }),
            'offers' => $this->whenLoaded('offers', function () {
                return $this->offers->map(function ($offer) {
                    return [
                        'id' => $offer->id,
                        'quantity' => (int) ($offer->pivot->quantity ?? 1),
                        'reward_type' => $offer->reward_type,
                        'points' => (int) ($offer->points ?? 0),
                        'offer_start_date' => \format_date_app_tz($offer->offer_start_date),
                        'offer_end_date' => \format_date_app_tz($offer->offer_end_date),
                        'is_active' => (bool) $offer->is_active,
                    ];
                });
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    // Merge product and variant into one object
                    $productVariant = null;
                    
                    if (($item->relationLoaded('product') && $item->product) || 
                        ($item->relationLoaded('variant') && $item->variant)) {
                        $productVariant = [];
                        
                        // Add product data
                        if ($item->relationLoaded('product') && $item->product) {
                            $productVariant['product_id'] = $item->product->id;
                            $productVariant['product_name_ar'] = $item->product->name_ar;
                            $productVariant['product_name_en'] = $item->product->name_en;
                            $productVariant['product_sku'] = $item->product->sku;
                        }
                        
                        // Add variant data
                        if ($item->relationLoaded('variant') && $item->variant) {
                            $productVariant['variant_id'] = $item->variant->id;
                            $productVariant['variant_size'] = $item->variant->size;
                            $productVariant['variant_sku'] = $item->variant->sku;
                            $productVariant['variant_price'] = (float) $item->variant->price;
                            $productVariant['variant_is_active'] = (bool) $item->variant->is_active;
                        }
                    }
                    
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                        'is_offer' => (bool) $item->is_offer,
                        'product_variant' => $productVariant,
                    ];
                });
            }),
            'invoice' => $this->whenLoaded('invoice', function () {
                // Calculate total before discounts (sum of all order items)
                $totalBeforeDiscounts = (float) $this->total_amount;
                
                // Get payment_link from invoice or from order's temporary attribute (for backward compatibility)
                $paymentLink = $this->invoice->payment_link ?? ($this->payment_link ?? null);
                
                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'total_before_discounts' => $totalBeforeDiscounts,
                    'tax_amount' => (float) $this->invoice->tax_amount,
                    'delivery_fee' => (float) ($this->invoice->delivery_fee ?? 0),
                    'offer_discount' => (float) $this->invoice->offer_discount,
                    'used_points' => (int) $this->invoice->used_points,
                    'points_discount' => (float) $this->invoice->points_discount,
                    'total_discount' => (float) $this->invoice->total_discount,
                    'amount_due' => (float) $this->invoice->amount_due,
                    'status' => $this->invoice->status,
                    'paid_at' => \format_datetime_app_tz($this->invoice->paid_at),
                    'payment_link' => $paymentLink,
                ];
            }),
            'delivery' => $this->whenLoaded('delivery', function () {
                return [
                    'id' => $this->delivery->id,
                    'payment_method' => $this->delivery->payment_method,
                    'delivery_address' => $this->delivery->delivery_address,
                    'block' => $this->delivery->block,
                    'street' => $this->delivery->street,
                    'house_number' => $this->delivery->house_number,
                    'delivery_datetime' => \format_datetime_app_tz($this->delivery->delivery_datetime),
                    'received_datetime' => \format_datetime_app_tz($this->delivery->received_datetime),
                    'delivery_status' => $this->delivery->delivery_status,
                    'notes' => $this->delivery->notes,
                ];
            }),
            'payment_link' => $this->when(
                !empty($this->payment_link ?? null),
                $this->payment_link ?? null
            ),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}

