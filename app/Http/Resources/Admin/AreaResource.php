<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaResource extends JsonResource
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
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'is_active' => $this->is_active,
            'governorate' => $this->whenLoaded('governorate', function () {
                return [
                    'id' => $this->governorate->id,
                    'name_en' => $this->governorate->name_en,
                    'name_ar' => $this->governorate->name_ar,
                    'country' => $this->when($this->governorate->relationLoaded('country'), function () {
                        return [
                            'id' => $this->governorate->country->id,
                            'name_en' => $this->governorate->country->name_en,
                            'name_ar' => $this->governorate->country->name_ar,
                        ];
                    }),
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
