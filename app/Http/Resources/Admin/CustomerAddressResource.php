<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
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
            'country' => $this->whenLoaded('country', function () {
                return [
                    'id' => $this->country->id,
                    'name_en' => $this->country->name_en,
                    'name_ar' => $this->country->name_ar,
                ];
            }),
            'governorate' => $this->whenLoaded('governorate', function () {
                return [
                    'id' => $this->governorate->id,
                    'name_en' => $this->governorate->name_en,
                    'name_ar' => $this->governorate->name_ar,
                ];
            }),
            'area' => $this->whenLoaded('area', function () {
                return [
                    'id' => $this->area->id,
                    'name_en' => $this->area->name_en,
                    'name_ar' => $this->area->name_ar,
                ];
            }),
            'street' => $this->street,
            'house' => $this->house,
            'block' => $this->block,
            'floor' => $this->floor,
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

