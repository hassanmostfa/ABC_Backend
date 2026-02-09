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
        $data = [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type ?? 'house',
            'lat' => $this->lat ? (float) $this->lat : null,
            'lng' => $this->lng ? (float) $this->lng : null,
            'phone_number' => $this->phone_number,
            'additional_directions' => $this->additional_directions,
            'address_label' => $this->address_label,
            'formatted_address' => $this->formatted_address ?? null,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country->id,
                'name_en' => $this->country->name_en,
                'name_ar' => $this->country->name_ar,
            ]),
            'governorate' => $this->whenLoaded('governorate', fn () => [
                'id' => $this->governorate->id,
                'name_en' => $this->governorate->name_en,
                'name_ar' => $this->governorate->name_ar,
            ]),
            'area' => $this->whenLoaded('area', fn () => [
                'id' => $this->area->id,
                'name_en' => $this->area->name_en,
                'name_ar' => $this->area->name_ar,
            ]),
            'street' => $this->street,
            'house' => $this->house,
            'block' => $this->block,
            'floor' => $this->floor,
            'building_name' => $this->building_name,
            'apartment_number' => $this->apartment_number,
            'company' => $this->company,
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];

        return $data;
    }
}

