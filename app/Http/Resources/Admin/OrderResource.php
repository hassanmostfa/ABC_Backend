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
            'charity_id' => $this->charity_id,
            'type' => $this->charity_id ? 'charity' : ($this->customer_id ? 'customer' : null),
            'payment_method' => $this->payment_method,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'offer_id' => $this->offer_id,
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
            'offer' => $this->whenLoaded('offer', function () {
                return [
                    'id' => $this->offer->id,
                    'reward_type' => $this->offer->reward_type,
                    'points' => (int) ($this->offer->points ?? 0),
                    'offer_start_date' => $this->offer->offer_start_date?->toISOString(),
                    'offer_end_date' => $this->offer->offer_end_date?->toISOString(),
                    'is_active' => (bool) $this->offer->is_active,
                ];
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
                
                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'total_before_discounts' => $totalBeforeDiscounts,
                    'tax_amount' => (float) $this->invoice->tax_amount,
                    'offer_discount' => (float) $this->invoice->offer_discount,
                    'used_points' => (int) $this->invoice->used_points,
                    'points_discount' => (float) $this->invoice->points_discount,
                    'total_discount' => (float) $this->invoice->total_discount,
                    'amount_due' => (float) $this->invoice->amount_due,
                    'status' => $this->invoice->status,
                    'paid_at' => $this->invoice->paid_at?->toISOString(),
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
                    'delivery_datetime' => $this->delivery->delivery_datetime?->toISOString(),
                    'received_datetime' => $this->delivery->received_datetime?->toISOString(),
                    'delivery_status' => $this->delivery->delivery_status,
                    'notes' => $this->delivery->notes,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

