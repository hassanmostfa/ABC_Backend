<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
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
            'code' => $this->code,
            'type' => $this->type ?? 'general',
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'minimum_order_amount' => (float) ($this->minimum_order_amount ?? 0),
            'usage_limit' => $this->usage_limit,
            'used_count' => (int) $this->used_count,
            'starts_at' => \format_datetime_app_tz($this->starts_at),
            'expires_at' => \format_datetime_app_tz($this->expires_at),
            'is_active' => (bool) $this->is_active,
            'customer_id' => $this->customer_id,
            'product_variant_ids' => $this->whenLoaded('productVariants', fn () => $this->productVariants->pluck('id')->values()->all(), []),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}
