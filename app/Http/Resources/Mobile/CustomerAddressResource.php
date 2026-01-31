<?php

namespace App\Http\Resources\Mobile;

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
            'type' => $this->type,
            'lat' => $this->lat ? (float) $this->lat : null,
            'lng' => $this->lng ? (float) $this->lng : null,
            'phone_number' => $this->phone_number,
            'additional_directions' => $this->additional_directions,
            'address_label' => $this->address_label,
            'formatted_address' => $this->formatted_address,
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
        ];

        if ($this->type === 'apartment') {
            $data['building_name'] = $this->building_name;
            $data['apartment_number'] = $this->apartment_number;
            $data['floor'] = $this->floor;
            $data['street'] = $this->street;
        } elseif ($this->type === 'house') {
            $data['house'] = $this->house;
            $data['street'] = $this->street;
            $data['block'] = $this->block;
        } elseif ($this->type === 'office') {
            $data['building_name'] = $this->building_name;
            $data['company'] = $this->company;
            $data['floor'] = $this->floor;
            $data['street'] = $this->street;
            $data['block'] = $this->block;
        }

        return $data;
    }
}
