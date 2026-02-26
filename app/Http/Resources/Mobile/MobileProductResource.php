<?php

namespace App\Http\Resources\Mobile;

use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileProductResource extends JsonResource
{
    use ManagesFileUploads;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name_ar' => $this->category->name_ar,
                    'name_en' => $this->category->name_en,
                ];
            }),
            'subcategory' => $this->whenLoaded('subcategory', function () {
                if (!$this->subcategory) {
                    return null;
                }
                return [
                    'id' => $this->subcategory->id,
                    'name_ar' => $this->subcategory->name_ar,
                    'name_en' => $this->subcategory->name_en,
                ];
            }),
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'sku' => $this->sku,
            'is_active' => (bool) $this->is_active,
            'variants' => $this->whenLoaded('variants', function () {
                return $this->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'size' => $variant->size,
                        'sku' => $variant->sku,
                        'short_item' => $variant->short_item,
                        'quantity' => (int) $variant->quantity,
                        'price' => (float) $variant->price,
                        'image' => $variant->image ? $this->getFileUrl($variant->image, 'public', 'no-image.png') : null,
                        'is_active' => (bool) $variant->is_active,
                        'created_at' => \format_datetime_app_tz($variant->created_at),
                        'updated_at' => \format_datetime_app_tz($variant->updated_at),
                    ];
                });
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}
