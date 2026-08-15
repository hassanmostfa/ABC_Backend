<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_sequence' => (int) $this->order_sequence,
            'month_number' => (int) $this->month_number,
            'order_in_month' => (int) $this->order_in_month,
            'scheduled_delivery_date' => $this->scheduled_delivery_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'items' => $this->when($this->relationLoaded('items'), function () use ($lang) {
                return $this->items->map(function ($item) use ($lang) {
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
                            'name' => $lang === 'ar' ? $product->name_ar : $product->name_en,
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
                })->values();
            }),
        ];
    }

    private function getLanguage(Request $request): string
    {
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));

        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }
}
